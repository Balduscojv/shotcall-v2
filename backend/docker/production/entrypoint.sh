#!/bin/sh
set -eu

PORT="${PORT:-10000}"
export PORT

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

# No plano Free do Render não existe pre-deploy command, então as migrations rodam aqui.
# Desligue com RUN_MIGRATIONS=false se um dia houver mais de uma instância.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force --no-interaction
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
