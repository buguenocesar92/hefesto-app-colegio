# ---------------------------------------------------------------------------
# Imagen de la API (Laravel + php-fpm)
# ---------------------------------------------------------------------------
#
# Las versiones están fijadas por tag exacto. Una etiqueta como `8.4` puede
# cambiar el contenido sin avisar; un digest no.
#
# Referencia de versiones:
#   php:8.4.26-cli-alpine3.23    -> etapa de compilación de dependencias
#   php:8.4.26-fpm-alpine3.23    -> capa de ejecución y CLI
#
# PENDIENTE ANTES DE PRODUCCIÓN
# -----------------------------
# Fijar estas imágenes por digest SHA256. Una etiqueta exacta es reproducible
# casi siempre, pero el digest es reproducible siempre. Está anotado en
# docs/10.
#
# ADVERTENCIA HONESTA: esta imagen NO se construyó. No había Docker en el
# entorno donde se escribió el código. Ver docs/10.
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# Etapa 1: dependencias con el autoloader optimizado.
# ---------------------------------------------------------------------------
FROM php:8.4.26-cli-alpine3.23 AS dependencias

RUN apk add --no-cache git unzip libpq-dev icu-dev oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pcntl bcmath intl mbstring

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Se copian solo los manifiestos primero para que la capa de dependencias se
# reutilice cuando no cambian las versiones del composer.json.
COPY composer.json composer.lock* ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---------------------------------------------------------------------------
# Etapa 2: la aplicación.
# ---------------------------------------------------------------------------
FROM php:8.4.26-fpm-alpine3.23 AS app

# `nginx` queda como servidor web interno de la imagen de la API. El servidor
# público es Caddy (deploy/caddy/Caddyfile).
# `curl` se instala solo para las comprobaciones de salud del orquestador.
RUN apk add --no-cache nginx supervisor curl \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pcntl bcmath intl mbstring opcache \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

COPY --from=dependencias /var/www/html /var/www/html

COPY docker/php.ini /usr/local/etc/php/conf.d/colegio.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# El usuario de php-fpm no es root.
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]