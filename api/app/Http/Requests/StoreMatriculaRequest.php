<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de la matrícula de un alumno.
 *
 * `numero_matricula` es opcional a propósito: si no viene, el servidor
 * propone el siguiente. Si viene, es porque la escuela ya lo tenía
 * asignado, y eso significa que la referencia importa.
 */
class StoreMatriculaRequest extends FormRequest
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

            'anio_escolar' => ['required', 'integer', 'min:2000', 'max:2100'],

            'numero_matricula' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],

            'fecha_matricula' => ['nullable', 'date', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
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
            'anio_escolar.required' => 'Indique el año escolar.',
            'numero_matricula.regex' => 'El número de matrícula solo admite letras, números y guiones.',
        ];
    }
}