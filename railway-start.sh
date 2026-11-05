#!/bin/sh
set -eu
PORT="${PORT:-8080}"
case "$PORT" in
  *[!0-9]*|'') echo "PORT must be a numeric port" >&2; exit 1 ;;
esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
  echo "PORT must be between 1 and 65535" >&2
  exit 1
fi
export PORT
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
