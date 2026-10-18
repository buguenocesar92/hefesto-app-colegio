<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CanalComunicacion;
use App\Enums\TipoDestinatario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación del alta de una comunicación.
 *
 * DOS COSAS QUE VALE LA PENA MIRAR
 * --------------------------------
 *
 * 1. El par (tipo de destinatario, identificador) tiene que corresponder.
 *    Un aviso de tipo "curso" sin `curso_id` no se puede enviar; uno con
 *    `nivel_id` de más es un error, no un dato ignorado. Un endpoint que
 *    acepta campos que no usa es un endpoint que después se lee mal.
 *
 * 2. Los mensajes están en español y están escritos para quien los lee
 *    (el maestro de comunicaciones), no para quien desarrolla.
 */
class StoreComunicacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización fina la decide `ComunicacionPolicy` en el
        // controlador. Acá se exige que haya sesión, que es lo que este
        // FormRequest puede comprobar por sí solo.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plantilla_id' => ['nullable', 'uuid'],

            'titulo' => ['required', 'string', 'min:4', 'max:180'],
            'cuerpo' => ['required', 'string', 'min:10', 'max:20000'],

            'canal' => ['required', Rule::enum(CanalComunicacion::class)],

            'tipo_destinatario' => ['required', Rule::enum(TipoDestinatario::class)],

            'curso_id' => ['nullable', 'uuid'],
            'nivel_id' => ['nullable', 'uuid'],
            'alumno_id' => ['nullable', 'uuid'],
            'apoderado_id' => ['nullable', 'uuid'],

            'requiere_acuse' => ['sometimes', 'boolean'],

            // Mandar el aviso de inmediato. Un borrador no genera
            // destinatarios: se resuelve al enviar, no antes.
            'enviar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Escriba un título para el aviso.',
            'titulo.min' => 'El título es demasiado corto.',
            'cuerpo.required' => 'Escriba el texto del aviso.',
            'cuerpo.min' => 'El texto del aviso es demasiado corto.',
            'canal.required' => 'Indique por qué canal salió el aviso.',
            'tipo_destinatario.required' => 'Indique a quién va dirigido el aviso.',
            'curso_id.uuid' => 'El curso indicado no es válido.',
            'nivel_id.uuid' => 'El nivel indicado no es válido.',
        ];
    }

    /**
     * Comprueba que el identificador que se envía sea el que corresponde al
     * tipo de destinatario.
     */
    public function withValidator($validador): void
    {
        $validador->after(function ($validador): void {
            $tipo = TipoDestinatario::tryFrom((string) $this->input('tipo_destinatario'));

            if ($tipo === null) {
                return;
            }

            // Exactamente el identificador que el tipo pide, y ninguno más.
            $reglas = [
                'curso' => ['curso_id', 'apoderado_id', 'alumno_id', 'nivel_id'],
                'nivel' => ['nivel_id', 'curso_id', 'apoderado_id', 'alumno_id'],
                'apoderado' => ['apoderado_id', 'curso_id', 'nivel_id', 'alumno_id'],
                'alumno' => ['alumno_id', 'curso_id', 'nivel_id', 'apoderado_id'],
            ];

            $esperado = $reglas[$tipo->value][0];
            $resto = array_slice($reglas[$tipo->value], 1);

            if ($this->filled($esperado) === false) {
                $validador->errors()->add(
                    $esperado,
                    'Un aviso de tipo "' . $tipo->etiqueta() . '" necesita su destino correspondiente.'
                );

                return;
            }

            foreach ($resto as $campo) {
                if ($this->filled($campo)) {
                    $validador->errors()->add(
                        $campo,
                        'Este campo no corresponde a un aviso de tipo "' . $tipo->etiqueta() . '".'
                    );
                }
            }
        });
    }
}