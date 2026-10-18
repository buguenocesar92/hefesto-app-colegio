<?php

declare(strict_types=1);

return [

    /*
    |-----------------------------------------------------------------------
    | Ajustes propios del Colegio
    |-----------------------------------------------------------------------
    |
    | Cosas que son de esta aplicación y no de Laravel. Se leen con
    | `config('colegio....')` para que el valor de un despliegue viva en el
    | `.env` y no en el código.
    |
    */

    /*
    |-----------------------------------------------------------------------
    | Retención de la bitácora de accesos
    |-----------------------------------------------------------------------
    |
    | La Ley 19.628 obliga a poder demostrar quién accedió a los datos
    | personales. Por eso la bitácora existe. Pero una bitácora que se
    | conserva para siempre es ella misma un repositorio de datos personales
    | (navegador, IP, ruta y, sobre todo, a qué alumno se accedió).
    |
    | Por eso hay que decidir cuánto tiempo se guarda. El valor por defecto es
    | un año, que es generoso, y la decisión FINAL es del colegio con su
    | asesoría legal. Ver docs/08.
    |
    */
    'retencion_bitacora_horas' => (int) env('COLEGIO_RETENCION_BITACORA_HORAS', 8760),

    /*
    |-----------------------------------------------------------------------
    | Roles que pueden leer conducta
    |-----------------------------------------------------------------------
    |
    | Deliberadamente configurable, y NO una constante en el código.
    |
    | Leer el registro de incidentes de conducta es la decisión más sensible
    | de todo el sistema, y hay colegios chicos donde la dirección y la
    | orientadora son la misma persona: si el rol no está en esta lista, esa
    | persona queda sin poder ver los incidentes que ella misma registró.
    |
    | Está separado por esa razón: para cambiarlo basta con cambiar una
    | variable de entorno y volver a cargar la configuración, no tocar
    | código. El valor por defecto es el más acotado.
    |
    | Ver docs/03, sección "Modelo de permisos".
    |
    */
    'roles_que_leen_conducta' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('COLEGIO_ROLES_CONDUCTA', 'direccion,orientacion,docente'))
    ))),

    /*
    |-----------------------------------------------------------------------
    | Tamaño máximo de página
    |-----------------------------------------------------------------------
    |
    | Techo duro para que nadie pida toda la tabla de una sola vez.
    |
    */
    'por_pagina_maximo' => (int) env('COLEGIO_POR_PAGINA_MAXIMO', 100),

];