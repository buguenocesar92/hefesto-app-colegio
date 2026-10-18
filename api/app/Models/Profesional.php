<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Profesional del colegio.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Profesional extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'profesionales';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'apellido',
        'rut',
        'correo',
        'telefono',
        'cargo',
        'es_docente',
        'activo',
    ];

    protected function casts(): array
    {
        return ['es_docente' => 'boolean', 'activo' => 'boolean'];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_profesional')
            ->withPivot('tipo')
            ->withTimestamps();
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }
}