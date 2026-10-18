<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación de una plantilla de comunicación. */
class PlantillaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'canal' => $this->canal?->value,
            'canal_etiqueta' => $this->canal?->etiqueta(),
            'asunto' => $this->asunto,
            'cuerpo' => $this->cuerpo,
            'requiere_acuse' => $this->requiere_acuse,
            'activa' => $this->activa,
        ];
    }
}