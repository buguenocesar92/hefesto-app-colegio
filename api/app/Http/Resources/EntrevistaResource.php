<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de una entrevista o reunión.
 *
 * El motivo se expone: es lo que la familia necesita ver para saber de qué
 * se trata y decidir si tiene que modificar su agenda.
 */
class EntrevistaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'fecha' => $this->fecha?->toDateString(),
            'hora' => $this->hora,
            'lugar' => $this->lugar,
            'estado' => $this->estado?->value,
            'estado_etiqueta' => $this->estado?->etiqueta(),
            'motivo' => $this->motivo,

            'alumno' => $this->whenLoaded('alumno', fn (): ?array => $this->alumno === null ? null : [
                'id' => $this->alumno->id,
                'nombre_completo' => $this->alumno->nombreCompleto(),
                'iniciales' => $this->alumno->iniciales(),
            ]),

            'apoderado' => $this->whenLoaded('apoderado', fn (): ?array => $this->apoderado === null ? null : [
                'id' => $this->apoderado->id,
                'nombre_completo' => $this->apoderado->nombreCompleto(),
            ]),

            'profesional' => $this->whenLoaded('profesional', fn (): ?array => $this->profesional === null ? null : [
                'id' => $this->profesional->id,
                'nombre_completo' => $this->profesional->nombreCompleto(),
                'cargo' => $this->profesional->cargo,
            ]),

            'tiene_acta' => $this->relationLoaded('acta') && $this->acta !== null,

            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}