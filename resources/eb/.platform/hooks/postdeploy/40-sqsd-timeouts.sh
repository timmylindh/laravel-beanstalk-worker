#!/bin/bash
set -euo pipefail

# Load EB environment variables if available
if [ -f /opt/elasticbeanstalk/support/envvars ]; then
    . /opt/elasticbeanstalk/support/envvars
fi

# Only apply on worker environments
if [ "${IS_WORKER:-false}" != "true" ]; then
    echo "[worker-timeouts] IS_WORKER is not true; skipping sqsd timeout config"
    exit 0
fi

WORKER_TIMEOUT_SEC=${WORKER_TIMEOUT:-300}
if ! [[ "$WORKER_TIMEOUT_SEC" =~ ^[0-9]+$ ]]; then
    echo "[worker-timeouts] Invalid WORKER_TIMEOUT='$WORKER_TIMEOUT_SEC', defaulting to 300"
    WORKER_TIMEOUT_SEC=300
fi

# Keep sqsd inactivity_timeout slightly above Nginx fastcgi_read_timeout (which is worker_timeout+30)
SQSD_INACTIVITY_TIMEOUT=$((WORKER_TIMEOUT_SEC + 30))

# Prefer AL2023 yaml config under /etc/aws-sqsd.d; fallback to legacy json under /etc/aws-sqsd
if [ -d /etc/aws-sqsd.d ]; then
    cat >/etc/aws-sqsd.d/default.yaml <<YAML
http_path: /worker/queue
http_port: 80
https: false
connect_timeout: 10
inactivity_timeout: ${SQSD_INACTIVITY_TIMEOUT}
YAML
    echo "[worker-timeouts] Wrote /etc/aws-sqsd.d/default.yaml with inactivity_timeout=${SQSD_INACTIVITY_TIMEOUT}"
elif [ -d /etc/aws-sqsd ]; then
    cat >/etc/aws-sqsd/daemon.cfg <<JSON
{
  "http_path": "/worker/queue",
  "http_port": 80,
  "https": false,
  "connect_timeout": 10,
  "inactivity_timeout": ${SQSD_INACTIVITY_TIMEOUT}
}
JSON
    echo "[worker-timeouts] Wrote /etc/aws-sqsd/daemon.cfg with inactivity_timeout=${SQSD_INACTIVITY_TIMEOUT}"
else
    # Create AL2023-style directory and write yaml
    mkdir -p /etc/aws-sqsd.d
    cat >/etc/aws-sqsd.d/default.yaml <<YAML
http_path: /worker/queue
http_port: 80
https: false
connect_timeout: 10
inactivity_timeout: ${SQSD_INACTIVITY_TIMEOUT}
YAML
    echo "[worker-timeouts] Created /etc/aws-sqsd.d/default.yaml with inactivity_timeout=${SQSD_INACTIVITY_TIMEOUT}"
fi

echo "[worker-timeouts] Reloading systemd units"
systemctl daemon-reload || true

echo "[worker-timeouts] Attempting to restart sqsd daemon via systemd"
RESTARTED=false
for UNIT in aws-sqsd.service aws-sqsd@default.service sqsd.service eb-sqs.service eb-sqsd.service; do
    if systemctl list-unit-files | awk '{print $1}' | grep -qx "$UNIT"; then
        echo "[worker-timeouts] Restarting $UNIT"
        if systemctl restart "$UNIT"; then
            RESTARTED=true
            break
        fi
    fi
done

if [ "$RESTARTED" = false ]; then
    echo "[worker-timeouts] Systemd unit not found; attempting gentle reload (SIGHUP)"
    # Try HUP to trigger reload if supported. Do NOT kill or manually start; EB agent owns lifecycle.
    if pgrep -f aws-sqsd >/dev/null 2>&1; then
        echo "[worker-timeouts] Sending SIGHUP to aws-sqsd"
        pkill -HUP -f aws-sqsd || true
    elif pgrep -f sqsd >/dev/null 2>&1; then
        echo "[worker-timeouts] Sending SIGHUP to sqsd"
        pkill -HUP -f sqsd || true
    else
        echo "[worker-timeouts] No sqsd process found; relying on EB agent to (re)start it"
    fi
fi


