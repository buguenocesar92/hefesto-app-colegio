<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\BitacoraAcceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Escribe en la bitácora quién accedió a qué.
 *
 * SEPARACIÓN DE CANALES
 * ---------------------
 * Después del acceso (con colegio): tabla `bitacora_accesos`, modo
 * append-only, sin interfaz de lectura.
 *
 * Antes del acceso (sin colegio): canal de log `colegio_seguridad`, en JSON.
 * Un intento de acceso sin sesión no tiene colegio, y `bitacora_accesos.
 * tenant_id` es NOT NULL porque no hay hechos sin colegio.
 *
 * UN FALLO AL ESCRIBIR NO TUMBA LA PETICIÓN
 * -----------------------------------------
 * Se avisa por log y la respuesta sigue su curso. Perder una línea de
 * bitácora es menos malo que perder una matrícula o un acuse de recibo, que
 * es lo que el colegio viene a resolver.
 */
class AuditorDeAcceso
{
    /** Peticiones que se anotan. El resto es ruido. */
    private const RUTAS_INTERESANTES = [
        'alumnos',
        'comunicaciones',
        'comunicacion_recibos',
        'asistencias',
        'conducta_incidentes',
        'entrevistas',
        'actas_reunion',
        'matriculas',
        'consentimientos',
    ];

    /**
     * @param  string  $ruta  Ruta de la petición, por ejemplo `api/v1/alumnos/8`.
     * @param  int  $codigoEstado  Código HTTP de la respuesta.
     */
    public function registrarPeticion(Request $request, int $codigoEstado): void
    {
        $usuario = $request->user();

        if ($usuario === null || $usuario->tenant_id === null) {
            return;
        }

        try {
            $ruta = $request->path();

            BitacoraAcceso::create([
                'tenant_id' => $usuario->tenant_id,
                'usuario_id' => $usuario->id,
                'accion' => $this->accionDe($ruta, $request->method()),
                'ruta' => mb_substr($ruta, 0, 255),
                'metodo' => $request->method(),
                'direccion_ip' => $request->ip(),
                'agente_usuario' => mb_substr((string) $request->userAgent(), 0, 2000),
                'codigo_estado' => $codigoEstado,
                'exito' => $codigoEstado < 400,
                'registrado_en' => now(),
            ]);
        } catch (Throwable $excepcion) {
            Log::channel(config('logging.channels.colegio_seguridad.name', 'stack'))->warning(
                'No se pudo escribir la bitácora de accesos.',
                ['error' => $excepcion->getMessage()]
            );
        }
    }

    /**
     * Rechazos que ocurren antes del acceso: cuenta desactivada, colegio
     * inexistente, intento de alta sin invitación.
     *
     * @param  array<string, mixed>  $contexto
     */
    public function registrarRechazo(string $evento, array $contexto = []): void
    {
        Log::channel(config('logging.channels.colegio_seguridad.name', 'stack'))->warning($evento, $contexto);
    }

    /**
     * Acción legible a partir de la ruta.
     *
     * No intenta ser un vocabulario completo: clasifica por el recurso
     * interesante que aparece en la ruta, y si no hay ninguno, deja
     * "otra".
     */
    private function accionDe(string $ruta, string $metodo): string
    {
        $recurso = null;

        foreach (self::RUTAS_INTERESANTES as $interesante) {
            if (str_contains($ruta, $interesante)) {
                $recurso = $interesante;

                break;
            }
        }

        if ($recurso === null) {
            return 'otra';
        }

        return match ($metodo) {
            'GET' => 'ver_' . $recurso,
            'POST' => 'crear_' . $recurso,
            'PATCH', 'PUT' => 'modificar_' . $recurso,
            default => $metodo . '_' . $recurso,
        };
    }
}