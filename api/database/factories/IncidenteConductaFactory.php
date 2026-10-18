<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategoriaIncidente;
use App\Enums\GravedadIncidente;
use App\Models\Curso;
use App\Models\IncidenteConducta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidenteConducta>
 */
class IncidenteConductaFactory extends Factory
{
    protected $model = IncidenteConducta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gravedad = fake()->randomElement([
            GravedadIncidente::Leve,
            GravedadIncidente::Media,
            GravedadIncidente::Grave,
        ]);

        return [
            'curso_id' => Curso::factory(),
            'fecha' => fake()->dateTimeBetween('-20 days', 'now'),
            'categoria' => fake()->randomElement([
                CategoriaIncidente::Convivencia,
                CategoriaIncidente::Academico,
                CategoriaIncidente::Seguridad,
                CategoriaIncidente::Otro,
            ]),
            'gravedad' => $gravedad,
            'descripcion' => 'Interrumpió la clase en dos ocasiones. Se conversó en el momento y no se repitió.',
            'medida' => $gravedad === GravedadIncidente::Leve
                ? 'Conversación privada con el alumno.'
                : 'Se cita a la familia para entrevista.',
            'registrado_en' => now(),
            'notificado_apoderado' => false,
            'comunicacion_id' => null,
        ];
    }

    /** Incidente leve: no obliga a comunicar. */
    public function leve(): static
    {
        return $this->state(fn (): array => [
            'gravedad' => GravedadIncidente::Leve,
            'medida' => 'Conversación privada con el alumno.',
        ]);
    }

    /** Incidente grave: obliga a comunicar y a entrevistar. */
    public function grave(): static
    {
        return $this->state(fn (): array => [
            'gravedad' => GravedadIncidente::Grave,
            'medida' => 'Se cita a la familia para entrevista. Se avisa por comunicación.',
        ]);
    }

    /** Ya comunicado a la familia. */
    public function notificado(): static
    {
        return $this->state(fn (): array => ['notificado_apoderado' => true]);
    }
}