<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representación de un profesional del colegio. */
class ProfesionalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_completo' => $this->nombreCompleto(),
            'cargo' => $this->cargo,
            'es_docente' => $this->es_docente,
            'activo' => $this->activo,
            'correo' => $this->correo,
            'telefono' => $this->telefono,
        ];
    }
}