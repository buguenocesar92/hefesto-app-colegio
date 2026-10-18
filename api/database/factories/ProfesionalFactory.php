<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profesional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profesional>
 */
class ProfesionalFactory extends Factory
{
    protected $model = Profesional::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->firstNameFemale(),
            'apellido' => fake()->lastName(),
            'rut' => null,
            'correo' => fake()->unique()->safeEmail(),
            'telefono' => '+56 9 ' . fake()->numerify('#### ####'),
            'cargo' => fake()->randomElement(['Docente', 'Jefatura UTP', 'Orientadora', 'Psicóloga']),
            'es_docente' => true,
            'activo' => true,
        ];
    }
}