<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CategoriaIncidente;
use App\Enums\GravedadIncidente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación del registro de un incidente de conducta.
 *
 * LA DESCRIPCIÓN ES LO IMPORTANTE
 * ------------------------------
 * Se exige un mínimo de 15 caracteres. Es deliberado: una descripción de
 * cinco letras ("se portó mal") no sirve ni para orientar al alumno ni para
 * responder a una familia tres meses después, que es justo cuando se lee.
 */
class StoreIncidenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alumno_id' => ['required', 'uuid'],
            'curso_id' => ['required', 'uuid'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],

            'categoria' => ['required', Rule::enum(CategoriaIncidente::class)],
            'gravedad' => ['required', Rule::enum(GravedadIncidente::class)],

            'descripcion' => ['required', 'string', 'min:15', 'max:4000'],
            'medida' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alumno_id.required' => 'Indique el alumno.',
            'curso_id.required' => 'Indique el curso.',
            'categoria.required' => 'Indique la categoría del incidente.',
            'gravedad.required' => 'Indique la gravedad.',
            'descripcion.required' => 'Describa lo ocurrido.',
            'descripcion.min' => 'La descripción es demasiado corta para servir como registro. Cuente qué pasó con algo más de detalle.',
        ];
    }
}