#!/bin/sh
# Boots the API container ("app") or one of its helpers ("worker", "scheduler").
# Only "app" runs the installation; the helpers wait until it has finished.
set -e

cd /var/www/backend

ROLE="${CONTAINER_ROLE:-app}"
KEY_FILE=storage/app/.app-key
READY_FILE=storage/app/.ready

if [ "$ROLE" = "app" ]; then
    rm -f "$READY_FILE"
    mkdir -p storage/app storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views

    # Generate APP_KEY once and keep it in the shared storage volume.
    if [ -z "$APP_KEY" ] && [ ! -s "$KEY_FILE" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$KEY_FILE"
    fi
else
    echo "Waiting for the app container to finish installing..."
    i=0
    until [ -f "$READY_FILE" ]; do
        i=$((i + 1))
        [ "$i" -gt 180 ] && echo "Timed out waiting for app" && exit 1
        sleep 2
    done
fi

if [ -z "$APP_KEY" ]; then
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

# Commands run later with "docker compose exec" do not inherit the exported
# key, so leave it in the container's .env (the rest comes from compose).
echo "APP_KEY=$APP_KEY" > .env

if [ "$ROLE" = "app" ]; then
    echo "Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT}..."
    until pg_isready -q -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USERNAME"; do sleep 1; done

    php artisan app:instalar --no-interaction
    chown -R www-data:www-data storage bootstrap/cache
    touch "$READY_FILE"
    exec "$@"
fi

exec su-exec www-data "$@"
