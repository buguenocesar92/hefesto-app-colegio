<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EstadoAsistencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación del registro de asistencia.
 *
 * UNA REGLA QUE PARECE ESTRICTA Y NO LO ES
 * -----------------------------------------
 * "Justificado" exige `justificacion`. Lo mismo le pasa a cualquier estado
 * que se mande sin explicación: la validación acepta la justificación en
 * todos los casos, pero la exige cuando el estado la necesita. Se puso acá
 * y no en el controlador para que el mensaje de error llegue al formulario.
 *
 * El alcance del docente NO se valida acá: depende de qué cursos tiene a
 * cargo, y eso lo decide `AlcanceDeAlumnos` en el controlador.
 */
class StoreAsistenciaRequest extends FormRequest
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
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'curso_id' => ['required', 'uuid'],

            'registros' => ['required', 'array', 'min:1', 'max:100'],
            'registros.*.alumno_id' => ['required', 'uuid'],
            'registros.*.estado' => ['required', Rule::enum(EstadoAsistencia::class)],
            'registros.*.justificacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'Indique la fecha del registro.',
            'fecha.before_or_equal' => 'No se puede registrar asistencia de una fecha futura.',
            'curso_id.required' => 'Indique el curso.',
            'registros.required' => 'Indique al menos un alumno.',
            'registros.min' => 'Indique al menos un alumno.',
            'registros.max' => 'Demasiados alumnos en un solo registro.',
            'registros.*.estado.required' => 'Falta el estado de asistencia de algún alumno.',
        ];
    }

    /**
     * Exige la justificación cuando el estado la necesita.
     */
    public function withValidator($validador): void
    {
        $validador->after(function ($validador): void {
            foreach ((array) $this->input('registros', []) as $indice => $registro) {
                if (! is_array($registro)) {
                    continue;
                }

                $estado = EstadoAsistencia::tryFrom((string) ($registro['estado'] ?? ''));

                if ($estado !== null && $estado->exigeJustificacion()) {
                    $justificacion = trim((string) ($registro['justificacion'] ?? ''));

                    if ($justificacion === '') {
                        $validador->errors()->add(
                            'registros.' . $indice . '.justificacion',
                            'Una ausencia justificada necesita la razón.'
                        );
                    }
                }
            }
        });
    }
}