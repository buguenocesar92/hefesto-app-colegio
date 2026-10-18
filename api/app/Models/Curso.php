<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Curso: "3° Básico A".
 *
 * Es la unidad de trabajo del colegio. Los avisos van a un curso, la
 * asistencia se registra por curso, y el alcance de un docente sale de los
 * cursos que tiene a cargo.
 *
 * AISLAMIMIENTO: RLS por `tenant_id`.
 */
class Curso extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'cursos';

    protected $fillable = ['tenant_id', 'nivel_id', 'nombre', 'sigla', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'orden' => 'integer'];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * Profesionales con carga en este curso.
     *
     * De esta relación sale el alcance del docente: no de su cargo, sino de
     * los cursos donde efectivamente teaches.
     */
    public function profesionales(): BelongsToMany
    {
        return $this->belongsToMany(Profesional::class, 'curso_profesional')
            ->withPivot('tipo')
            ->withTimestamps();
    }
}