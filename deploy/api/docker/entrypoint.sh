#!/bin/sh
# ---------------------------------------------------------------------------
# Entrada de la imagen de la API
# ---------------------------------------------------------------------------
#
# QUÉ HACE
# --------
# 1. Espera a que PostgreSQL esté disponible. En un `docker compose` la API
#    puede arrancar antes que la base, y un fallo de conexión al arrancar deja
#    el contenedor en un estado raro que hay que diagnosticar a mano.
#
# 2. Corre las migraciones si corresponde.
#
# 3. VERIFICA EL AISLAMIENTO antes de aceptar tráfico. Si la RLS no está
#    puesta, la aplicación funciona igual y está completamente abierta entre
#    colegios. Este paso es lo que evita desplegar eso sin darse cuenta.
#
# 4. Limpia cachés y arranca.
#
# OJO CON EL PUNTO 3
# ------------------
# `colegio:verificar-aislamiento` devuelve código de salida 1 si algo falla, y
# el script aborta con el contenedor. Es deliberado: es preferible no
# levantar antes que levantar abierto.
# ---------------------------------------------------------------------------

set -e

echo "-> Esperando a PostgreSQL..."

until php -r "
    try {
        new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT') ?: '5432', getenv('DB_DATABASE')),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_TIMEOUT => 3]
        );
    } catch (Throwable $e) {
        exit(1);
    }
" 2>/dev/null; do
    sleep 2
done

echo "-> PostgreSQL disponible."

if [ "${COLEGIO_CORRER_MIGRACIONES:-false}" = "true" ]; then
    echo "-> Corriendo migraciones..."
    php artisan migrate --force

    echo "-> Verificando aislamiento entre colegios..."
    php artisan colegio:verificar-aislamiento

    if [ "${COLEGIO_SEMBRAR:-false}" = "true" ]; then
        echo "-> Cargando datos de ejemplo..."
        php artisan db:seed --force
    fi
fi

echo "-> Limpiando cachés..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "-> API lista."

exec "$@"