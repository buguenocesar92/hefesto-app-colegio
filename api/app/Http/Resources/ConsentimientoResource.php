<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de un consentimiento.
 *
 * El estado (`vigente` o revocado) va explícito, porque es el dato que
 * responde "¿podían tratar estos datos cuando lo hicieron?".
 */
class ConsentimientoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alcance' => $this->alcance,
            'version_documento' => $this->version_documento,
            'otorgado_por' => $this->otorgado_por,
            'fecha_otorgamiento' => $this->fecha_otorgamiento?->toDateString(),
            'evidencia_ruta' => $this->evidencia_ruta,
            'revocado_en' => $this->revocado_en?->toIso8601String(),
            'vigente' => $this->vigente(),

            'apoderado' => $this->whenLoaded('apoderado', fn (): ?array => $this->apoderado === null ? null : [
                'id' => $this->apoderado->id,
                'nombre_completo' => $this->apoderado->nombreCompleto(),
            ]),

            // Nulo cuando el consentimiento cubre a todos los hijos.
            'alumno' => $this->whenLoaded('alumno', fn (): ?array => $this->alumno === null ? null : [
                'id' => $this->alumno->id,
                'nombre_completo' => $this->alumno->nombreCompleto(),
            ]),
        ];
    }
}