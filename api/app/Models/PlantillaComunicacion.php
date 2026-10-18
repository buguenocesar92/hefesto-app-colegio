<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CanalComunicacion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plantilla de comunicación.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class PlantillaComunicacion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'plantillas_comunicacion';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'canal',
        'asunto',
        'cuerpo',
        'requiere_acuse',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'canal' => CanalComunicacion::class,
            'requiere_acuse' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function comunicaciones(): HasMany
    {
        return $this->hasMany(Comunicacion::class);
    }
}