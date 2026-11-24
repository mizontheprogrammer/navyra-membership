#!/bin/sh
set -eu
export APP_ENV=production
export APP_DEBUG=false
export SESSION_SECURE_COOKIE=true
export TRUST_PROXY_HEADERS=true
export LOG_CHANNEL=stderr
export APP_URL="${RENDER_EXTERNAL_URL:-${APP_URL:-}}"
export PORT="${PORT:-10000}"
case "$PORT" in ''|*[!0-9]*) echo 'Invalid PORT' >&2; exit 1;; esac
case "$APP_URL" in https://*) ;; *) echo 'A public HTTPS APP_URL is required.' >&2; exit 1;; esac
: "${APP_KEY:?Set a stable production APP_KEY in Render.}"
: "${DB_CONNECTION:?Configure the hosted database.}"
# Render secret files remain outside the source repository and image layers.
if [ -f /etc/secrets/database-ca.pem ]; then export DB_SSLROOTCERT=/etc/secrets/database-ca.pem; fi
printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:10000>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf
php artisan config:cache --no-interaction
php artisan migrate --force --no-interaction
php artisan app:bootstrap-production-admin --no-interaction
php artisan view:cache --no-interaction
chown -R www-data:www-data storage bootstrap/cache
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/navyra.conf
