<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RolUsuario;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta con acceso al sistema.
 *
 * Extiende `Illuminate\Foundation\Auth\User` porque es la clase que
 * implementa `Authenticatable`: sin eso, el guard de sesión no acepta el
 * modelo. No extiende `Model` directamente.
 *
 * NO hay contraseña. La identidad la demuestra Authentik (OIDC). Lo que
 * decide el acceso a los datos es el `rol`, que es del colegio.
 *
 * `authentik_sub` está en `$hidden`: no debe salir en ningún Resource.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
class Usuario extends Authenticatable
{
    use HasFactory;
    use HasUuids;

    protected $table = 'usuarios';

    protected $fillable = [
        'tenant_id',
        'authentik_sub',
        'correo',
        'nombre',
        'apellido',
        'telefono',
        'rol',
        'profesional_id',
        'apoderado_id',
        'activo',
        'ultimo_acceso',
    ];

    protected $hidden = [
        'authentik_sub',
    ];

    protected function casts(): array
    {
        return [
            'rol' => RolUsuario::class,
            'activo' => 'boolean',
            'ultimo_acceso' => 'datetime',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'tenant_id');
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    public function apoderado(): BelongsTo
    {
        return $this->belongsTo(Apoderado::class);
    }

    /** Comunicaciones enviadas por esta persona. */
    public function comunicaciones(): HasMany
    {
        return $this->hasMany(Comunicacion::class, 'remitente_usuario_id');
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    // ------------------------------------------------------------------
    // Atajos del rol.
    //
    // Existen para que las Policies lean como reglas y no como
    // comparaciones de cadenas. El detalle de qué puede hacer cada rol está
    // en el enum y en docs/03.
    // ------------------------------------------------------------------

    /** ¿Es una familia? */
    public function esApoderado(): bool
    {
        return $this->rol === RolUsuario::Apoderado;
    }

    /** ¿Es una persona del colegio? */
    public function esDelColegio(): bool
    {
        return $this->rol?->esDelColegio() ?? false;
    }

    /** ¿Administra el colegio? */
    public function administra(): bool
    {
        return $this->rol?->administra() ?? false;
    }

    /** ¿Registra asistencia (es docente)? */
    public function esDocente(): bool
    {
        return $this->rol?->registraAsistencia() ?? false;
    }

    /**
     * ¿Puede LEER conducta?
     *
     * Distinto de "¿la registra?": orientación registra y lee; dirección
     * lee sin registrar. Ver `RolUsuario::leeConducta()`.
     */
    public function veConducta(): bool
    {
        return $this->rol?->leeConducta() ?? false;
    }

    /** ¿Registra incidentes de conducta? */
    public function registraConducta(): bool
    {
        return $this->rol?->registraConducta() ?? false;
    }

    /** ¿Envía comunicaciones? */
    public function enviaComunicaciones(): bool
    {
        return $this->rol?->enviaComunicaciones() ?? false;
    }

    /** ¿Gestiona la agenda de entrevistas? */
    public function gestionaAgenda(): bool
    {
        return $this->rol?->gestionaAgenda() ?? false;
    }

    /**
     * ¿Ve el listado completo de alumnos del colegio?
     *
     * Dirección y administración. Un docente NO: ve los suyos, que es lo
     * que devuelve `AlcanceDeAlumnos::apoderablesPor()`.
     */
    public function veTodosLosAlumnos(): bool
    {
        return $this->rol?->administra() ?? false;
    }
}