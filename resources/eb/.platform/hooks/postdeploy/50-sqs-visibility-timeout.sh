#!/bin/bash
set -euo pipefail

# Load EB environment variables if available
if [ -f /opt/elasticbeanstalk/support/envvars ]; then
    . /opt/elasticbeanstalk/support/envvars
fi

# Only apply on worker environments
if [ "${IS_WORKER:-false}" != "true" ]; then
    echo "[worker-timeouts] IS_WORKER is not true; skipping SQS visibility timeout"
    exit 0
fi

# Requires AWS CLI on instance role with sqs:SetQueueAttributes
if ! command -v aws >/dev/null 2>&1; then
    echo "[worker-timeouts] AWS CLI not available; skipping SQS visibility timeout"
    exit 0
fi

WORKER_TIMEOUT_SEC=${WORKER_TIMEOUT:-300}
if ! [[ "$WORKER_TIMEOUT_SEC" =~ ^[0-9]+$ ]]; then
    echo "[worker-timeouts] Invalid WORKER_TIMEOUT='$WORKER_TIMEOUT_SEC', defaulting to 300"
    WORKER_TIMEOUT_SEC=300
fi

# Choose a buffer above end-to-end HTTP path; default +100s
VISIBILITY_TIMEOUT=$((WORKER_TIMEOUT_SEC + 100))

# Determine queue URL: prefer SQS_QUEUE_URL env; else try EB named queue env var
QUEUE_URL=${SQS_QUEUE_URL:-}
if [ -z "$QUEUE_URL" ]; then
    # Fallback: try using EB environment/process default queue URL via metadata file if present
    if [ -f /opt/elasticbeanstalk/tasks/taillogs.d/sqsd-queue-url ]; then
        QUEUE_URL=$(cat /opt/elasticbeanstalk/tasks/taillogs.d/sqsd-queue-url || true)
    fi
fi

if [ -n "$QUEUE_URL" ]; then
    echo "[worker-timeouts] Setting SQS VisibilityTimeout=${VISIBILITY_TIMEOUT} on $QUEUE_URL"
    if ! aws sqs set-queue-attributes --queue-url "$QUEUE_URL" --attributes VisibilityTimeout="$VISIBILITY_TIMEOUT"; then
        echo "[worker-timeouts] Failed to set VisibilityTimeout on $QUEUE_URL" >&2
    fi
else
    echo "[worker-timeouts] No SQS_QUEUE_URL provided; skipping SQS visibility timeout"
fi


