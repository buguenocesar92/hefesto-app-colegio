<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de una ficha de familia.
 *
 * El RUT y la dirección no viajan a un apoderado que entra a ver a su hijo:
 * el mismo endpoint sirve para el colegio y para la familia, y la
 * diferencia la hace la Policy. Para no exponer RUT de terceros por error,
 * el endpoint de familia usa una versión reducida (docs/09).
 */
class ApoderadoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_completo' => $this->nombreCompleto(),
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'correo' => $this->correo,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
            'rut' => $this->rut,
            'activo' => $this->activo,

            'cantidad_alumnos' => $this->whenCounted('alumnos'),

            'alumnos' => $this->whenLoaded('alumnos', fn () => $this->alumnos
                ->map(fn ($alumno): array => [
                    'id' => $alumno->id,
                    'nombre_completo' => $alumno->nombreCompleto(),
                    'vinculo' => $alumno->pivot->vinculo,
                    'es_responsable_legal' => (bool) $alumno->pivot->es_responsable_legal,
                ])
                ->values()),
        ];
    }
}