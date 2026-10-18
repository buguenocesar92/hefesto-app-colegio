<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de un incidente de conducta.
 *
 * La descripción y la medida son lo más delicado de todo el sistema. Este
 * recurso se sirve solo detrás de `IncidenteConductaPolicy`, que exige un
 * permiso de lectura de conducta, y nunca a un apoderado ni al maestro de
 * comunicaciones.
 */
class IncidenteResource extends JsonResource
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
            'categoria' => $this->categoria?->value,
            'categoria_etiqueta' => $this->categoria?->etiqueta(),
            'gravedad' => $this->gravedad?->value,
            'gravedad_etiqueta' => $this->gravedad?->etiqueta(),
            'descripcion' => $this->descripcion,
            'medida' => $this->medida,
            'notificado_apoderado' => $this->notificado_apoderado,
            'comunicacion_id' => $this->comunicacion_id,
            'exige_comunicacion' => $this->exigeComunicacion(),

            'alumno' => $this->whenLoaded('alumno', fn (): ?array => $this->alumno === null ? null : [
                'id' => $this->alumno->id,
                'nombre_completo' => $this->alumno->nombreCompleto(),
                'iniciales' => $this->alumno->iniciales(),
            ]),

            'curso' => $this->whenLoaded('curso', fn (): ?array => $this->curso === null ? null : [
                'id' => $this->curso->id,
                'sigla' => $this->curso->sigla,
                'nombre' => $this->curso->nombre,
            ]),

            'registrado_por' => $this->whenLoaded('registradoPor', fn (): ?array => $this->registradoPor === null ? null : [
                'id' => $this->registradoPor->id,
                'nombre_completo' => $this->registradoPor->nombreCompleto(),
                'rol' => $this->registradoPor->rol?->value,
            ]),

            'registrado_en' => $this->registrado_en?->toIso8601String(),
        ];
    }
}