<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoAsistencia;
use App\Models\Asistencia;
use App\Models\Curso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asistencia>
 */
class AsistenciaFactory extends Factory
{
    protected $model = Asistencia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $estado = fake()->randomElement([
            EstadoAsistencia::Presente,
            EstadoAsistencia::Ausente,
            EstadoAsistencia::Tarde,
            EstadoAsistencia::Justificado,
        ]);

        return [
            'curso_id' => Curso::factory(),
            'fecha' => fake()->dateTimeBetween('-10 days', 'now'),
            'estado' => $estado,
            // La justificación solo tiene sentido cuando el estado la exige.
            'justificacion' => $estado === EstadoAsistencia::Justificado
                ? 'Licencia médica entregada en secretaría.'
                : null,
            'registrado_en' => now(),
        ];
    }

    /** Presente. */
    public function presente(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoAsistencia::Presente,
            'justificacion' => null,
        ]);
    }

    /** Ausente sin justificación. */
    public function ausente(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoAsistencia::Ausente,
            'justificacion' => null,
        ]);
    }
}