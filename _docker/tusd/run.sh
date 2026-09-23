#!/bin/sh
set -eu

export AWS_ACCESS_KEY_ID="${AWS_ACCESS_KEY_ID:-${DO_SPACES_KEY:-}}"
export AWS_SECRET_ACCESS_KEY="${AWS_SECRET_ACCESS_KEY:-${DO_SPACES_SECRET:-}}"
export AWS_REGION="${AWS_REGION:-us-east-1}"

TUSD_HOST="${TUSD_HOST:-127.0.0.1}"
TUSD_PORT="${TUSD_PORT:-1080}"
TUSD_BIN="${TUSD_BIN:-tusd}"
BUCKET="${DO_SPACES_BUCKET:-}"
ENDPOINT="${DO_SPACES_ENDPOINT:-}"
SECRET="${TUSD_HOOKS_SECRET:-}"
HOOK_URL="${TUSD_HOOKS_URL:-}"
PART_SIZE="${TUSD_PART_SIZE:-8388608}"
MAX_SIZE="${TUSD_MAX_SIZE:-5368709120}"

if [ -z "$SECRET" ] && [ -n "${APP_KEY:-}" ]; then
    SECRET=$(printf '%s' "$APP_KEY" | sha256sum | cut -d ' ' -f1)
    echo "tusd: TUSD_HOOKS_SECRET unset, derived from APP_KEY" >&2
fi

if [ -z "$BUCKET" ] || [ -z "$ENDPOINT" ]; then
    echo "tusd: DO_SPACES_BUCKET and DO_SPACES_ENDPOINT are required" >&2
    exit 1
fi

if [ -z "$SECRET" ]; then
    echo "tusd: TUSD_HOOKS_SECRET is required" >&2
    exit 1
fi

if [ ! -x "$TUSD_BIN" ]; then
    echo "tusd: binary not executable: $TUSD_BIN" >&2
    exit 1
fi

if [ -z "$HOOK_URL" ]; then
    HOOK_URL="http://hooks:${SECRET}@127.0.0.1:${PORT:-80}/internal/tus-hooks"
fi

echo "tusd: listening on ${TUSD_HOST}:${TUSD_PORT}, hooks ${HOOK_URL%%@*}@…" >&2

exec "$TUSD_BIN" \
    -host="$TUSD_HOST" \
    -port="$TUSD_PORT" \
    -base-path=/tus/ \
    -behind-proxy \
    -s3-bucket="$BUCKET" \
    -s3-endpoint="$ENDPOINT" \
    -s3-object-prefix=tus-uploads/ \
    -s3-part-size="$PART_SIZE" \
    -s3-min-part-size="$PART_SIZE" \
    -max-size="$MAX_SIZE" \
    -hooks-http="$HOOK_URL" \
    -hooks-http-forward-headers=Cookie \
    -hooks-enabled-events=pre-create,post-finish
