<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoMatricula;
use App\Models\Curso;
use App\Models\Matricula;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matricula>
 */
class MatriculaFactory extends Factory
{
    protected $model = Matricula::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $anio = (int) date('Y');

        return [
            'curso_id' => Curso::factory(),
            'anio_escolar' => $anio,
            'numero_matricula' => $anio . '-' . fake()->unique()->numerify('####'),
            'estado' => EstadoMatricula::Matriculado,
            'fecha_matricula' => fake()->dateTimeBetween('-6 months', 'now'),
            'observaciones' => null,
        ];
    }

    /** Matrícula en curso retirado. */
    public function retirado(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoMatricula::Retirado]);
    }

    /** Matrícula pendiente de confirmar. */
    public function pendiente(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoMatricula::Pendiente]);
    }
}