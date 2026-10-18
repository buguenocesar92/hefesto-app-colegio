<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Asistencia;
use App\Models\Comunicacion;
use App\Models\Consentimiento;
use App\Models\Curso;
use App\Models\Entrevista;
use App\Models\IncidenteConducta;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\PlantillaComunicacion;
use App\Models\Profesional;
use App\Models\Usuario;
use App\Policies\AlumnoPolicy;
use App\Policies\AsistenciaPolicy;
use App\Policies\ComunicacionPolicy;
use App\Policies\GestionAdministrativaPolicy;
use App\Policies\IncidenteConductaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro de Policies.
 *
 * POR QUÉ HAY UN ARCHIVO DEDICADO
 * -------------------------------
 * En Laravel 11 en adelante, una Policy se puede descubrir sola por el
 * nombre si está en `app/Policies`. Eso funciona para `Alumno` →
 * `AlumnoPolicy`, pero NO para `GestionAdministrativaPolicy`, que sirve a
 * varias tablas a la vez. Por eso el registro es explícito: que quede claro
 * qué tabla responde por qué regla.
 *
 * Un Policy mal registrado no falla: simplemente nunca se ejecuta, y el
 * endpoint queda abierto. Con datos de menores, un policy que no corre es
 * peor que uno que deniega.
 */
class ColegioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Políticas con regla propia.
        Gate::policy(Alumno::class, AlumnoPolicy::class);
        Gate::policy(Comunicacion::class, ComunicacionPolicy::class);
        Gate::policy(IncidenteConducta::class, IncidenteConductaPolicy::class);
        Gate::policy(Asistencia::class, AsistenciaPolicy::class);

        // Catálogos de administración: una sola política para todos.
        Gate::policy([
            Nivel::class,
            Curso::class,
            Profesional::class,
            PlantillaComunicacion::class,
            Usuario::class,
            Consentimiento::class,
            Apoderado::class,
            Matricula::class,
            Entrevista::class,
        ], GestionAdministrativaPolicy::class);
    }
}