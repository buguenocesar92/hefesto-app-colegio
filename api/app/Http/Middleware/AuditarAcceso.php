<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\AuditorDeAcceso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota en la bitácora las peticiones que tocan datos sensibles.
 *
 * Se ejecuta DESPUÉS de la respuesta, con `try/finally`, para que también
 * queden registradas las peticiones que fallaron. Un 404 sobre un
 * identificador de otro colegio es, para la auditoría, un intento.
 *
 * Nunca deja que un fallo de la bitácora tumbe la petición: la pérdida de
 * una línea de auditoría es menos grave que la pérdida de una matrícula.
 */
class AuditarAcceso
{
    public function __construct(private readonly AuditorDeAcceso $auditor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $codigoEstado = 500;

        try {
            /** @var Response $respuesta */
            $respuesta = $next($request);
            $codigoEstado = $respuesta->getStatusCode();

            return $respuesta;
        } finally {
            $this->auditor->registrarPeticion($request, $codigoEstado);
        }
    }
}