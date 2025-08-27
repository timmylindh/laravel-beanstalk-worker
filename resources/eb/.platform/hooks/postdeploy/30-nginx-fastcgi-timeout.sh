#!/bin/bash
set -euo pipefail

# Load EB environment variables if available
if [ -f /opt/elasticbeanstalk/support/envvars ]; then
    . /opt/elasticbeanstalk/support/envvars
fi

TARGET_CONF="/var/app/current/.platform/nginx/conf.d/fastcgi-timeouts.conf"
RUNTIME_CONF="/etc/nginx/conf.d/fastcgi-timeouts.conf"

if [ "${IS_WORKER:-false}" = "true" ]; then
    # Compute a small buffer above WORKER_TIMEOUT (default +30s)
    WORKER_TIMEOUT_SEC=${WORKER_TIMEOUT:-300}
    if ! [[ "$WORKER_TIMEOUT_SEC" =~ ^[0-9]+$ ]]; then
        echo "[worker-timeouts] Invalid WORKER_TIMEOUT='$WORKER_TIMEOUT_SEC', defaulting to 300"
        WORKER_TIMEOUT_SEC=300
    fi
    FASTCGI_TIMEOUT=$((WORKER_TIMEOUT_SEC + 30))

    # Ensure a runtime conf exists with the desired timeout
    mkdir -p "$(dirname "$RUNTIME_CONF")"
    echo "fastcgi_read_timeout ${FASTCGI_TIMEOUT}s;" > "$RUNTIME_CONF"
    echo "[worker-timeouts] Set Nginx fastcgi_read_timeout=${FASTCGI_TIMEOUT}s in $RUNTIME_CONF"
else
    # Remove the runtime conf if present for non-worker envs
    if [ -f "$RUNTIME_CONF" ]; then
        rm -f "$RUNTIME_CONF"
        echo "[worker-timeouts] Removed $RUNTIME_CONF for non-worker env"
    fi
fi

echo "[worker-timeouts] Reloading Nginx"
systemctl reload nginx || systemctl restart nginx


