<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comunicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comunicacion>
 */
class ComunicacionFactory extends Factory
{
    protected $model = Comunicacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titulos = [
            'Reunión de apoderados',
            'Cambio de horario de educación física',
            'Calendario de evaluaciones',
            'Suspensión de clases por lluvia',
            'Entrega de documentos',
            'Invitación a acto familiar',
        ];

        return [
            'referencia' => 'COM-' . fake()->year() . '-' . fake()->unique()->numerify('######'),
            'titulo' => fake()->randomElement($titulos),
            'cuerpo' => 'Estimadas familias: se les informa que ' . fake()->sentence(12) . ' ',
            'canal' => fake()->randomElement(['correo', 'whatsapp', 'presencial', 'telefono']),
            'tipo_destinatario' => 'curso',
            'requiere_acuse' => true,
            'estado' => 'enviada',
            'enviada_en' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }

    /** Aviso que todavía no se ha enviado. */
    public function borrador(): static
    {
        return $this->state(fn (): array => [
            'estado' => 'borrador',
            'enviada_en' => null,
        ]);
    }

    /** Aviso que no pide confirmación de lectura. */
    public function sinAcuse(): static
    {
        return $this->state(fn (): array => ['requiere_acuse' => false]);
    }
}