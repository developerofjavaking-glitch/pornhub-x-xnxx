#!/bin/sh
set -e

if [ "${ENABLE_KEEPALIVE:-true}" = "true" ]; then
    echo "Starting keep-alive HTTP server on port ${PORT:-8080}..."
    php -S 0.0.0.0:"${PORT:-8080}" -t /app/public &
fi

echo "Starting Boom Host Bot (PHP)..."
exec php /app/src/bot.php
