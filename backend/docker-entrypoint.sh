#!/bin/sh
set -e

# Só o serviço "api" prepara o ambiente; fila e agendador esperam ele ficar saudável.
if [ "$SHOTCALL_PREPARE" = "1" ]; then
  if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
  fi
  [ -f .env ] || cp .env.example .env
  grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
  php artisan migrate --force
fi

exec "$@"
