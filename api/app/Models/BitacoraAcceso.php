<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de accesos: quién abrió qué dato.
 *
 * Append-only, sin interfaz de lectura. Se consulta por consola.
 *
 * No tiene factory a propósito: una bitácora que se pueda fabricar desde
 * una prueba es una bitácora en la que no se puede confiar.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class BitacoraAcceso extends Model
{
    use HasUuids;

    protected $table = 'bitacora_accesos';

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'accion',
        'ruta',
        'metodo',
        'direccion_ip',
        'agente_usuario',
        'codigo_estado',
        'exito',
        'registrado_en',
    ];

    protected function casts(): array
    {
        return [
            'registrado_en' => 'datetime',
            'exito' => 'boolean',
            'codigo_estado' => 'integer',
            'direccion_ip' => 'string',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}