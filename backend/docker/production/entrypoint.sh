#!/bin/sh
set -eu

PORT="${PORT:-10000}"
export PORT

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

# Rotas, views e eventos já vêm cacheados da imagem. A config depende das variáveis de ambiente.
php artisan config:cache
chown -R www-data:www-data storage bootstrap/cache

# Migrations (programa "migrate") e limpeza das travas do agendador (no comando "scheduler")
# rodam no supervisord, em paralelo com o servidor, para não segurar o boot.
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
