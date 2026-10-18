<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Nivel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
{
    protected $model = Curso::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grado = fake()->numberBetween(1, 8);
        $letra = fake()->randomElement(['A', 'B']);

        return [
            'nivel_id' => Nivel::factory(),
            'nombre' => $grado . '° Básico ' . $letra,
            'sigla' => $grado . $letra,
            'orden' => $grado,
            'activo' => true,
        ];
    }
}