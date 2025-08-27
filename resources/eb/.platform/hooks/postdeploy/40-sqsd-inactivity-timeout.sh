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
SQSD_VISIBILITY_TIMEOUT=$((WORKER_TIMEOUT_SEC + 100))

# Helpers to update keys without overwriting full files
update_yaml_key() {
    local file="$1"; local key="$2"; local value="$3"
    if grep -Eq "^[[:space:]]*${key}:" "$file"; then
        sed -i -E "s|^([[:space:]]*)${key}:.*|\\1${key}: ${value}|" "$file"
    else
        printf "%s: %s\n" "$key" "$value" >> "$file"
    fi
}

update_json_key() {
    local file="$1"; local key="$2"; local value="$3"
    if grep -Eq "\"${key}\"[[:space:]]*:" "$file"; then
        sed -i -E "s|(\"${key}\"[[:space:]]*:[[:space:]]*)[0-9]+|\\1${value}|" "$file"
    else
        if grep -Eq '^\{[[:space:]]*\}$' "$file"; then
            printf "{\n  \"%s\": %s\n}\n" "$key" "$value" > "$file"
        else
            sed -i -E "s|}\s*$|,\n  \"$key\": $value\n}|" "$file"
        fi
    fi
}

# Prefer AL2023 yaml config under /etc/aws-sqsd.d; fallback to legacy json under /etc/aws-sqsd
if [ -f /etc/aws-sqsd.d/default.yaml ]; then
    update_yaml_key "/etc/aws-sqsd.d/default.yaml" "inactivity_timeout" "$SQSD_INACTIVITY_TIMEOUT"
    update_yaml_key "/etc/aws-sqsd.d/default.yaml" "visibility_timeout" "$SQSD_VISIBILITY_TIMEOUT"
    echo "[worker-timeouts] Updated /etc/aws-sqsd.d/default.yaml inactivity_timeout=${SQSD_INACTIVITY_TIMEOUT}, visibility_timeout=${SQSD_VISIBILITY_TIMEOUT}"
    UPDATED_FILE="/etc/aws-sqsd.d/default.yaml"
elif [ -f /etc/aws-sqsd/daemon.cfg ]; then
    update_json_key "/etc/aws-sqsd/daemon.cfg" "inactivity_timeout" "$SQSD_INACTIVITY_TIMEOUT"
    update_json_key "/etc/aws-sqsd/daemon.cfg" "visibility_timeout" "$SQSD_VISIBILITY_TIMEOUT"
    echo "[worker-timeouts] Updated /etc/aws-sqsd/daemon.cfg inactivity_timeout=${SQSD_INACTIVITY_TIMEOUT}, visibility_timeout=${SQSD_VISIBILITY_TIMEOUT}"
    UPDATED_FILE="/etc/aws-sqsd/daemon.cfg"
else
    echo "[worker-timeouts] WARNING: Neither /etc/aws-sqsd.d/default.yaml nor /etc/aws-sqsd/daemon.cfg exists; nothing updated" >&2
fi

echo "[worker-timeouts] Reloading systemd and restarting aws-sqsd"
systemctl daemon-reload || true
service sqsd restart || systemctl restart sqsd || true


