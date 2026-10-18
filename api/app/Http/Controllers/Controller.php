<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Controlador base.
 *
 * Solo junta los traits. Las decisiones de autorización NO viven acá: viven
 * en `App\Policies` y se aplican con `$this->authorize()` en cada controlador,
 * para que se vea en la pantalla qué permiso se está exigiendo.
 */
class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;
}