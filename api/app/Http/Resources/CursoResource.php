<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación de un curso. */
class CursoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'sigla' => $this->sigla,
            'orden' => $this->orden,
            'activo' => $this->activo,
            'nivel' => $this->whenLoaded('nivel', fn (): ?array => $this->nivel === null ? null : [
                'id' => $this->nivel->id,
                'nombre' => $this->nivel->nombre,
            ]),
        ];
    }
}