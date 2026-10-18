<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de un acta de reunión o entrevista.
 *
 * El acta es el documento que el colegio puede mostrar a una familia cuando
 * pregunta qué se acordó. Es largo a propósito: no se recorta, porque su
 * valor es ser el registro completo.
 */
class ActaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entrevista_id' => $this->entrevista_id,
            'titulo' => $this->titulo,
            'resumen' => $this->resumen,
            'acuerdos' => $this->acuerdos,
            'fecha' => $this->fecha?->toDateString(),

            'autor' => $this->whenLoaded('autor', fn (): ?array => $this->autor === null ? null : [
                'id' => $this->autor->id,
                'nombre_completo' => $this->autor->nombreCompleto(),
                'rol' => $this->autor->rol?->etiqueta(),
            ]),

            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}