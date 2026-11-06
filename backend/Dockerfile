# Imagem de desenvolvimento do Laravel: a mesma serve a API, a fila e o agendador (compose.yaml).
# O código não é copiado: a pasta backend/ é montada em /app, então editar no host vale na hora.
FROM php:8.4-cli

# Extensões que o app usa (Postgres, intl, gd para imagens, zip para o Composer, pcntl para a fila).
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.7.34/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql intl gd zip bcmath pcntl redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Mesmos limites de upload do PHP local: banner em GIF de até 15 MB (CosmeticCatalogController).
RUN { \
      echo 'upload_max_filesize=16M'; \
      echo 'post_max_size=20M'; \
      echo 'memory_limit=512M'; \
    } > /usr/local/etc/php/conf.d/shotcall.ini

COPY docker-entrypoint.sh /usr/local/bin/shotcall-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/shotcall-entrypoint && chmod +x /usr/local/bin/shotcall-entrypoint

WORKDIR /app
ENTRYPOINT ["shotcall-entrypoint"]
# --no-reload libera os PHP_CLI_SERVER_WORKERS (o Next faz várias chamadas em paralelo).
# Código PHP alterado vale na hora; mudança no .env pede `docker compose restart api`.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
