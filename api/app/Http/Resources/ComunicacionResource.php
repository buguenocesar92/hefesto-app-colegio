<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de una comunicación.
 *
 * El recurso expone los datos de CONTENIDO y los de ENTREGA por separado:
 *
 *  - el contenido (qué se dijo) lo ve cualquiera a quien va dirigido;
 *  - la entrega (quién confirmó, cuándo) solo si la Policy `verAcuses` lo
 *    permite, y por eso va en `when` con una comprobación explícita.
 *
 * Esa separación no es cosmética: el acuse es evidencia de la relación con
 * una familia concreta, y tiene más peso que el texto del aviso.
 */
class ComunicacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $puedeVerAcuses = $request->user()?->esDelColegio() === true;

        return [
            'id' => $this->id,
            'referencia' => $this->referencia,
            'titulo' => $this->titulo,
            'cuerpo' => $this->cuerpo,
            'canal' => $this->canal?->value,
            'canal_etiqueta' => $this->canal?->etiqueta(),
            'tipo_destinatario' => $this->tipo_destinatario?->value,
            'tipo_destinatario_etiqueta' => $this->tipo_destinatario?->etiqueta(),
            'estado' => $this->estado?->value,
            'estado_etiqueta' => $this->estado?->etiqueta(),
            'requiere_acuse' => $this->requiere_acuse,
            'enviada_en' => $this->enviada_en?->toIso8601String(),

            'curso' => $this->whenLoaded('curso', fn (): ?array => $this->curso === null ? null : [
                'id' => $this->curso->id,
                'nombre' => $this->curso->nombre,
                'sigla' => $this->curso->sigla,
            ]),

            'nivel' => $this->whenLoaded('nivel', fn (): ?array => $this->nivel === null ? null : [
                'id' => $this->nivel->id,
                'nombre' => $this->nivel->nombre,
            ]),

            'remitente' => $this->whenLoaded('remitente', fn (): ?array => $this->remitente === null ? null : [
                'id' => $this->remitente->id,
                'nombre_completo' => $this->remitente->nombreCompleto(),
                'rol' => $this->remitente->rol?->value,
            ]),

            'destinatarios' => $this->whenLoaded('destinatarios', fn () => $this->destinatarios
                ->map(fn ($destinatario): array => [
                    'apoderado_id' => $destinatario->apoderado_id,
                    'nombre_completo' => $destinatario->relationLoaded('apoderado')
                        ? $destinatario->apoderado->nombreCompleto()
                        : null,
                ])
                ->values()),

            // El resumen de entrega solo lo ve el colegio.
            'entrega' => $this->when(
                $puedeVerAcuses && $this->relationLoaded('destinatarios'),
                fn (): array => [
                    'total_destinatarios' => $this->destinatarios->count(),
                    'total_recibos' => $this->relationLoaded('recibos') ? $this->recibos->count() : $this->recibos()->count(),
                    'pendientes' => max(
                        0,
                        $this->destinatarios->count()
                            - ($this->relationLoaded('recibos') ? $this->recibos->count() : $this->recibos()->count()),
                    ),
                    'porcentaje_acuse' => $this->porcentajeAcuse(),
                ],
            ),

            'recibos' => $this->when(
                $puedeVerAcuses && $this->relationLoaded('recibos'),
                fn () => $this->recibos
                    ->map(fn ($recibo): array => [
                        'apoderado_id' => $recibo->apoderado_id,
                        'nombre_completo' => $recibo->relationLoaded('apoderado')
                            ? $recibo->apoderado->nombreCompleto()
                            : null,
                        'recibido_en' => $recibo->recibido_en?->toIso8601String(),
                        'medio' => $recibo->medio,
                    ])
                    ->values(),
            ),

            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}