<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de un alumno.
 *
 * EL RECORTE QUE PROTEGE A LOS MENORES
 * -----------------------------------
 * Este recurso expone la ficha completa: nombre, RUT, fecha de nacimiento,
 * dirección, contactos de emergencia.
 *
 * Por eso el endpoint que lo usa exige `verFichaCompleta` y no solo `ver`.
 * Un recurso con datos muy sensibles tiene que estar detrás de la
 * comprobación más fuerte, no de la más cómoda.
 *
 * `AlumnoResource` NO incluye `tenant_id`: para quien lo consume no
 * significa nada, y son menos cosas que revisar antes de tocar el archivo.
 */
class AlumnoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'nombre_completo' => $this->nombreCompleto(),
            'iniciales' => $this->iniciales(),
            'edad' => $this->edad(),
            'rut' => $this->rut,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'sexo' => $this->sexo,
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'fecha_ingreso' => $this->fecha_ingreso?->toDateString(),
            'activo' => $this->activo,

            'matricula' => $this->whenLoaded('matricula', fn (): array => $this->matricula === null ? [] : [
                'id' => $this->matricula->id,
                'numero_matricula' => $this->matricula->numero_matricula,
                'anio_escolar' => $this->matricula->anio_escolar,
                'estado' => $this->matricula->estado?->value,
                'estado_etiqueta' => $this->matricula->estado?->etiqueta(),
                'curso' => $this->matricula->relationLoaded('curso') ? [
                    'id' => $this->matricula->curso->id,
                    'nombre' => $this->matricula->curso->nombre,
                    'sigla' => $this->matricula->curso->sigla,
                ] : null,
            ]),

            'contactos_emergencia' => $this->whenLoaded('contactosEmergencia', fn () => $this->contactosEmergencia
                ->map(fn ($contacto): array => [
                    'id' => $contacto->id,
                    'nombre' => $contacto->nombre,
                    'parentesco' => $contacto->parentesco,
                    'telefono' => $contacto->telefono,
                    'prioridad' => $contacto->prioridad,
                ])
                ->values()),

            // Los apoderados se muestran solo en la ficha completa. El
            // listado no los trae, y con razón: ver quién es el contacto de
            // un alumno es parte de la ficha.
            'apoderados' => $this->whenLoaded('apoderados', fn () => $this->apoderados
                ->map(fn ($apoderado): array => [
                    'id' => $apoderado->id,
                    'nombre_completo' => $apoderado->nombreCompleto(),
                    'vinculo' => $apoderado->pivot->vinculo,
                    'es_responsable_legal' => (bool) $apoderado->pivot->es_responsable_legal,
                ])
                ->values()),

            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}