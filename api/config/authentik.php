<?php

declare(strict_types=1);

return [

    /*
    |-----------------------------------------------------------------------
    | Proveedor de identidad (Authentik, OIDC)
    |-----------------------------------------------------------------------
    |
    | El colegio no pide la identidad: la pide Authentik. Esta configuración
    | dice contra qué servidor se verifica el token y contra qué audiencia.
    |
    | `algoritmo` es el que se ESPERA, no el que llega en el token. Aceptar
    | el que venga cerraría el ataque de confusión de algoritmo (firmar con
    | la clave pública usando HS256). Ver App\Services\Auth\VerificadorIdToken.
    |
    */

    'issuer' => env('AUTHENTIK_ISSUER'),
    'client_id' => env('AUTHENTIK_CLIENT_ID'),
    'client_secret' => env('AUTHENTIK_CLIENT_SECRET'),
    'redirect_uri' => env('AUTHENTIK_REDIRECT_URI'),

    'scopes' => env('AUTHENTIK_SCOPES', 'openid profile email'),

    'algoritmo' => env('AUTHENTIK_ALGORITMO', 'RS256'),

    /*
    |-----------------------------------------------------------------------
    | Grupo de Authentik
    |-----------------------------------------------------------------------
    |
    | OJO, esto NO da permiso permanente.
    |
    | El grupo define el PRIMER alta de una cuenta nueva cuando el dominio
    | de correo coincide con el declarado por un colegio. Una vez dentro, lo
    | que decide es el `rol` en la tabla `usuarios`, que es del colegio.
    |
    | Si hoy Authentik deja de mandar el grupo de nadie, ninguna cuenta
    | existente pierde acceso. Esa es la intención.
    |
    */
    'grupo_administradores' => env('AUTHENTIK_GRUPO_ADMIN', 'colegio-direccion'),

    /*
    |-----------------------------------------------------------------------
    | Ventana de tolerancia de la petición de ingreso
    |-----------------------------------------------------------------------
    |
    | El intento de ingreso caduca después de estos minutos. Si alguien deja
    | la pestaña abierta entre que pide el ingreso y vuelve de Authentik, el
    | `state` guardado ya no sirve y hay que empezar de nuevo.
    |
    */
    'ventana_ingreso_minutos' => (int) env('AUTHENTIK_VENTANA_MINUTOS', 10),

];