<?php

declare(strict_types=1);

return [

    /*
    |-----------------------------------------------------------------------
    | Autenticación
    |-----------------------------------------------------------------------
    |
    | NO hay contraseñas en esta aplicación. El guard `web` usa la SESIÓN, y
    | la sesión la abre el callback OIDC de Authentik. No hay driver de
    | contraseñas que configurar, y eso es deliberado: el colegio no guarda
    | contraseñas de familias ni de empleados.
    |
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => null,
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'usuarios',
        ],
    ],

    /*
    |-----------------------------------------------------------------------
    | Modelo de usuario
    |-----------------------------------------------------------------------
    |
    | `App\Models\Usuario` extiende `Illuminate\Foundation\Auth\User`, que es
    | la clase que implementa `Authenticatable`. Si se extendiera el `Model`
    | pelado, el guard de sesión no lo aceptaría.
    |
    */

    'providers' => [
        'usuarios' => [
            'driver' => 'eloquent',
            'model' => App\Models\Usuario::class,
        ],
    ],

    /*
    |-----------------------------------------------------------------------
    | Contraseñas
    |-----------------------------------------------------------------------
    |
    | Se deja vacío a propósito. Si algún día se agrega un campo de
    | contraseña a `usuarios`, este bloque seguiría vacío y el driver de
    | contraseñas quedaría inaccesible.
    |
    */

    'passwords' => [],

    'password_timeout' => 10800,

];