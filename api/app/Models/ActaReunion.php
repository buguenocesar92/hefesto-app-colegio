<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acta de una reunión o entrevista.
 *
 * Es el documento que el colegio puede mostrar cuando una familia pregunta
 * qué se acordó. `entrevista_id` admite nulo: hay actas de reuniones de
 * curso que no tienen una entrevista individual asociada.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class ActaReunion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'actas_reunion';

    protected $fillable = [
        'tenant_id',
        'entrevista_id',
        'autor_usuario_id',
        'titulo',
        'resumen',
        'acuerdos',
        'fecha',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function entrevista(): BelongsTo
    {
        return $this->belongsTo(Entrevista::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autor_usuario_id');
    }
}