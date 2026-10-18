<?php

declare(strict_types=1);

return [

    /*
    |-----------------------------------------------------------------------
    | Sesión
    |-----------------------------------------------------------------------
    |
    | La cookie de sesión es la que sostiene todo el acceso. Cada línea de
    | esta configuración es una decisión de seguridad, no un valor por
    | defecto:
    |
    |   - HttpOnly: el JavaScript de la página no puede leerla. No hay token
    |     en `localStorage`.
    |   - Secure: solo viaja por HTTPS. En desarrollo va en `false` porque no
    |     hay certificado; en producción DEBE quedar en `true`.
    |   - SameSite=Lax: `strict` impediría el ingreso, porque la cookie no
    |     viaja en la redirección que vuelve desde Authentik.
    |   - use_strict_mode: un identificador de sesión que la aplicación no
    |     generó se rechaza.
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
    'encrypt' => env('SESSION_ENCRYPT', false),

    'files' => storage_path('framework/sessions'),

    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),

    'store' => env('SESSION_STORE'),

    'lottery' => [2, 100],

    'cookie' => env(
        'SESSION_COOKIE',
        \Illuminate\Support\Str::slug((string) env('APP_NAME', 'colegio'), '_') . '_session'
    ),

    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),

    'secure' => env('SESSION_SECURE_COOKIE'),
    'http_only' => env('SESSION_HTTP_ONLY', true),
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];