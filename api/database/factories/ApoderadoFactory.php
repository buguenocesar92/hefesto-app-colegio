<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Apoderado;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Apoderado>
 */
class ApoderadoFactory extends Factory
{
    protected $model = Apoderado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $apellido = fake()->lastName();

        return [
            'nombre' => fake()->firstNameFemale(),
            'apellido' => $apellido,
            'rut' => null,
            'correo' => Str::slug((string) fake()->unique()->userName(), '.') . '@ejemplo.cl',
            'telefono' => '+56 9 ' . fake()->numerify('#### ####'),
            'direccion' => fake()->streetAddress() . ', ' . fake()->city(),
            'activo' => true,
        ];
    }
}