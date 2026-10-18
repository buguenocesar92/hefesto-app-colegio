<?php

declare(strict_types=1);

return [

    'default' => env('DB_CONNECTION', 'pgsql'),

    /*
    |-----------------------------------------------------------------------
    | Conexión principal
    |-----------------------------------------------------------------------
    |
    | PostgreSQL, y no MySQL ni SQLite, porque el aislamiento entre
    | colegios depende de Row Level Security, que es específico de
    | PostgreSQL.
    |
    | REQUISITO QUE NO SE PUEDE OMITIR
    | ---------------------------------
    | El rol que usa ESTA conexión no puede ser superusuario ni tener
    | BYPASSRLS. Con cualquiera de las dos, PostgreSQL ignora las políticas
    | RLS por completo y un colegio vería los datos de todos los demás.
    |
    | Lo comprueba `php artisan colegio:verificar-aislamiento`, que hay que
    | correr en el despliegue. Ver docs/03 y docs/05.
    |
    */

    'connections' => [

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'colegio'),
            'username' => env('DB_USERNAME', 'colegio_app'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

    ],

    /*
    |-----------------------------------------------------------------------
    | Migraciones
    |-----------------------------------------------------------------------
    |
    | `schema` en el connection y no en `migrations`: aquí el esquema de
    | PostgreSQL es `public` (el normal). El aislamiento no se hace con
    | esquemas por colegio, sino con RLS. Ver docs/03, sección 2.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |-----------------------------------------------------------------------
    | Redis
    |-----------------------------------------------------------------------
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'colegio_db_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];