#!/bin/sh
set -eu

PORT="${PORT:-10000}"
export PORT

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

# Views e eventos já vêm cacheados da imagem. Config e rotas dependem das variáveis de ambiente
# (ex.: LOCAL_FILESYSTEM_SERVE decide se o Laravel registra a rota de arquivos locais em /storage,
# que tomaria o lugar do redirecionamento para o R2), então são cacheadas aqui.
php artisan config:cache
php artisan route:cache
chown -R www-data:www-data storage bootstrap/cache

# Migrations (programa "migrate") e limpeza das travas do agendador (no comando "scheduler")
# rodam no supervisord, em paralelo com o servidor, para não segurar o boot.
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
