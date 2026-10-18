<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación del registro de un acuse de recibo.
 *
 * El medio es obligatorio porque la diferencia entre "lo confirmó en el
 * portal" y "lo confirmó por teléfono" es justamente la que sirve para
 * responder a una familia. Un acuse sin medio es una anotación vaga.
 */
class RegistrarAcuseRequest extends FormRequest
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
            'medio' => ['required', 'string', 'in:web,correo,whatsapp,telefono,presencial'],
            'recibido_en' => ['nullable', 'date', 'before_or_equal:now'],
            'apoderado_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'medio.required' => 'Indique por qué medio se confirmó la recepción.',
            'medio.in' => 'El medio indicado no es válido.',
            'recibido_en.before_or_equal' => 'La fecha de recepción no puede ser futura.',
        ];
    }
}