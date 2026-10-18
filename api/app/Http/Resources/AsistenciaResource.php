<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de un registro de asistencia.
 *
 * La justificación viaja con el estado porque son dos caras del mismo dato:
 * "faltó" y "faltó por esto" se registran juntos o no significan nada.
 */
class AsistenciaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alumno_id' => $this->alumno_id,
            'curso_id' => $this->curso_id,
            'fecha' => $this->fecha?->toDateString(),
            'estado' => $this->estado?->value,
            'estado_etiqueta' => $this->estado?->etiqueta(),
            'cuenta_como_presente' => $this->cuentaComoPresente(),
            'es_ausencia' => $this->estado?->esAusencia() ?? false,
            'justificacion' => $this->justificacion,

            'alumno' => $this->whenLoaded('alumno', fn (): ?array => $this->alumno === null ? null : [
                'id' => $this->alumno->id,
                'nombre_completo' => $this->alumno->nombreCompleto(),
                'iniciales' => $this->alumno->iniciales(),
            ]),

            'registrado_por' => $this->whenLoaded('registradoPor', fn (): ?array => $this->registradoPor === null ? null : [
                'id' => $this->registradoPor->id,
                'nombre_completo' => $this->registradoPor->nombreCompleto(),
            ]),

            'registrado_en' => $this->registrado_en?->toIso8601String(),
        ];
    }
}