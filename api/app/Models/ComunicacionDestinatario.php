<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quién se dirige una comunicación.
 *
 * El puente entre el aviso y la familia. Un aviso a un curso de 35 alumnos
 * genera 35 filas: cada familia se enteró por su cuenta y puede confirmar
 * por su cuenta.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class ComunicacionDestinatario extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'comunicacion_destinatarios';

    protected $fillable = ['tenant_id', 'comunicacion_id', 'apoderado_id'];

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