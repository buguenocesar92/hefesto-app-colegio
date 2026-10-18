<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acuse de recibo: la prueba de que una familia confirmo la lectura.
 *
 * Es la fila que convierte "lo recibió" de una suposición en un hecho. Sin
 * ella, el colegio puede decir qué mandó, pero no que llegó.
 *
 * Una fila por familia y aviso, garantizado por el índice único. Si la
 * familia confirma otra vez, se actualiza la fecha en lugar de duplicar:
 * dos acuses del mismo aviso no dicen más que uno.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class ComunicacionRecibo extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'comunicacion_recibos';

    protected $fillable = [
        'tenant_id',
        'comunicacion_id',
        'apoderado_id',
        'recibido_en',
        'medio',
        'direccion_ip',
    ];

    protected function casts(): array
    {
        return [
            'recibido_en' => 'datetime',
            'direccion_ip' => 'string',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function comunicacion(): BelongsTo
    {
        return $this->belongsTo(Comunicacion::class);
    }

    public function apoderado(): BelongsTo
    {
        return $this->belongsTo(Apoderado::class);
    }
}