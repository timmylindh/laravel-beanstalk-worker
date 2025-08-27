#!/bin/bash
set -euo pipefail

# Load EB environment variables if available
if [ -f /opt/elasticbeanstalk/support/envvars ]; then
    . /opt/elasticbeanstalk/support/envvars
fi

# Only apply on worker environments
if [ "${IS_WORKER:-false}" != "true" ]; then
    echo "[worker-timeouts] IS_WORKER is not true; skipping PHP timeouts"
    exit 0
fi

WORKER_TIMEOUT_SEC=${WORKER_TIMEOUT:-300}

# Ensure numeric
if ! [[ "$WORKER_TIMEOUT_SEC" =~ ^[0-9]+$ ]]; then
    echo "[worker-timeouts] Invalid WORKER_TIMEOUT='$WORKER_TIMEOUT_SEC', defaulting to 300"
    WORKER_TIMEOUT_SEC=300
fi

# Set PHP-FPM hard kill timeout for a single request
echo "[worker-timeouts] Setting PHP-FPM request_terminate_timeout=${WORKER_TIMEOUT_SEC}s"
cat >/etc/php-fpm.d/zz-timeout.conf <<CONF
[www]
request_terminate_timeout = ${WORKER_TIMEOUT_SEC}s
CONF

echo "[worker-timeouts] Setting PHP max_execution_time=${WORKER_TIMEOUT_SEC}"
cat >/etc/php.d/zz-worker-timeouts.ini <<CONF
max_execution_time = ${WORKER_TIMEOUT_SEC}
CONF

echo "[worker-timeouts] Restarting php-fpm"
systemctl restart php-fpm || systemctl restart php82-php-fpm || true