<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de una matrícula.
 */
class MatriculaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_matricula' => $this->numero_matricula,
            'anio_escolar' => $this->anio_escolar,
            'estado' => $this->estado?->value,
            'estado_etiqueta' => $this->estado?->etiqueta(),
            'fecha_matricula' => $this->fecha_matricula?->toDateString(),
            'observaciones' => $this->observaciones,

            'alumno' => $this->whenLoaded('alumno', fn (): ?array => $this->alumno === null ? null : [
                'id' => $this->alumno->id,
                'nombre_completo' => $this->alumno->nombreCompleto(),
                'iniciales' => $this->alumno->iniciales(),
                'rut' => $this->alumno->rut,
            ]),

            'curso' => $this->whenLoaded('curso', fn (): ?array => $this->curso === null ? null : [
                'id' => $this->curso->id,
                'nombre' => $this->curso->nombre,
                'sigla' => $this->curso->sigla,
                'nivel' => $this->curso->relationLoaded('nivel') ? [
                    'id' => $this->curso->nivel->id,
                    'nombre' => $this->curso->nivel->nombre,
                ] : null,
            ]),

            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}