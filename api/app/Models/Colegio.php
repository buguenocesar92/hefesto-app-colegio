<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El colegio: el tenant.
 *
 * No lleva `tenant_id` ni RLS. Ver migración
 * 2026_10_17_000001_crear_tabla_colegios.php y docs/03, sección 4.
 */
class Colegio extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'colegios';

    protected $fillable = [
        'slug',
        'nombre',
        'razon_social',
        'rut',
        'dominio_correo',
        'correo_contacto',
        'telefono_contacto',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function niveles(): HasMany
    {
        return $this->hasMany(Nivel::class, 'tenant_id');
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class, 'tenant_id');
    }

    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class, 'tenant_id');
    }

    public function apoderados(): HasMany
    {
        return $this->hasMany(Apoderado::class, 'tenant_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'tenant_id');
    }

    public function comunicaciones(): HasMany
    {
        return $this->hasMany(Comunicacion::class, 'tenant_id');
    }
}