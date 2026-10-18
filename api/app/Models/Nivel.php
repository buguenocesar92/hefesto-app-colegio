<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nivel educativo: "Básica", "Media", "Parvularia".
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Nivel extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'niveles';

    protected $fillable = ['tenant_id', 'nombre', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'orden' => 'integer'];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }
}