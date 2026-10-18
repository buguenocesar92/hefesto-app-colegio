<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CatalogoController;
use App\Http\Controllers\AlumnoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComunicacionController;
use App\Http\Controllers\EntrevistaController;
use App\Http\Controllers\MatriculaController;
use App\Http\Controllers\RegistroController;
use Illuminate\Support\Facades\Route;

/*
|-------------------------------------------------------------------------------
| Rutas de la API del Colegio
|-------------------------------------------------------------------------------
|
| Todas cuelgan del grupo `web` de Laravel (ver bootstrap/app.php), así que
| comparten sesión, cookie y CSRF con el resto de la aplicación. El prefijo
| `/api/v1` se aplica acá y no en el esqueleto, para que quede a la vista que
| TODA la superficie de la API está versionada en una sola línea.
|
| Estas rutas las consume el BFF de Nuxt (web/server/api), que reenvía las
| cookies de la petición del navegador. Ver docs/01.
|
| EL MIDDLEWARE `auth`
| --------------------
| Sin sesión, responde 401 en formato JSON siempre que la petición envíe
| `Accept: application/json`, que es lo que hace el BFF.
|
| LO QUE NO ESTÁ EN LAS RUTAS
| ---------------------------
| Ningún endpoint de lectura o escritura de datos de negocio es público. Solo
| el ingreso, el callback y la salud. Y de los datos de negocio, ninguno es
| público ni siquiera para el mismo colegio: cada uno exige sesión y pasa
| por una Policy.
|
*/
Route::prefix('api/v1')->name('api.v1.')->group(function (): void {

    /*
    |---------------------------------------------------------------------------
    | Autenticación (OIDC contra Authentik)
    |---------------------------------------------------------------------------
    */
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::get('login', [AuthController::class, 'login'])->name('login');
        Route::get('callback', [AuthController::class, 'callback'])->name('callback');

        Route::middleware('auth')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('usuario', [AuthController::class, 'usuario'])->name('usuario');
        });
    });

    /*
    |---------------------------------------------------------------------------
    | Todo lo de abajo exige sesión
    |---------------------------------------------------------------------------
    */
    Route::middleware('auth')->group(function (): void {

        /*
        |-----------------------------------------------------------------------
        | Módulo 1: matrícula y alumnos
        |-----------------------------------------------------------------------
        */
        Route::get('alumnos', [AlumnoController::class, 'index'])->name('alumnos.index');
        Route::get('alumnos/{alumno}', [AlumnoController::class, 'show'])->name('alumnos.show');

        Route::get('matriculas', [MatriculaController::class, 'index'])->name('matriculas.index');
        Route::post('matriculas', [MatriculaController::class, 'store'])->name('matriculas.store');
        Route::patch('matriculas/{matricula}/estado', [MatriculaController::class, 'cambiarEstado'])
            ->name('matriculas.estado');

        /*
        |-----------------------------------------------------------------------
        | Módulo 2: comunicaciones (EL CENTRAL)
        |-----------------------------------------------------------------------
        |
        | Rutas deliberadamente separadas para lectura de acuses y registro de
        | acuses: son capacidades distintas y con Policies distintas.
        */
        Route::get('comunicaciones', [ComunicacionController::class, 'index'])->name('comunicaciones.index');
        Route::post('comunicaciones', [ComunicacionController::class, 'store'])->name('comunicaciones.store');
        Route::get('comunicaciones/{comunicacion}', [ComunicacionController::class, 'show'])
            ->name('comunicaciones.show');
        Route::post('comunicaciones/{comunicacion}/enviar', [ComunicacionController::class, 'enviar'])
            ->name('comunicaciones.enviar');

        Route::get('comunicaciones/{comunicacion}/recibos', [ComunicacionController::class, 'recibos'])
            ->name('comunicaciones.recibos.index');
        Route::post('comunicaciones/{comunicacion}/recibos', [ComunicacionController::class, 'registrarAcuse'])
            ->name('comunicaciones.recibos.store');

        /*
        |-----------------------------------------------------------------------
        | Módulo 3: entrevistas y reuniones
        |-----------------------------------------------------------------------
        */
        Route::get('entrevistas', [EntrevistaController::class, 'index'])->name('entrevistas.index');
        Route::post('entrevistas', [EntrevistaController::class, 'store'])->name('entrevistas.store');
        Route::get('entrevistas/{entrevista}', [EntrevistaController::class, 'show'])->name('entrevistas.show');
        Route::patch('entrevistas/{entrevista}/estado', [EntrevistaController::class, 'cambiarEstado'])
            ->name('entrevistas.estado');
        Route::get('entrevistas/{entrevista}/acta', [EntrevistaController::class, 'acta'])->name('entrevistas.acta');
        Route::post('entrevistas/{entrevista}/acta', [EntrevistaController::class, 'registrarActa'])
            ->name('entrevistas.acta.store');

        /*
        |-----------------------------------------------------------------------
        | Módulo 4: asistencia y conducta
        |-----------------------------------------------------------------------
        |
        | `asistencias` y `conducta_incidentes` están en endpoints distintos y
        | con Policies distintas. No es una cuestión de orden en el archivo: es
        | que el permiso para leer conducta no es el permiso para leer
        | asistencia. Ver docs/03.
        */
        Route::get('asistencias', [RegistroController::class, 'asistencias'])->name('asistencias.index');
        Route::post('asistencias', [RegistroController::class, 'registrarAsistencia'])->name('asistencias.store');

        Route::get('conducta', [RegistroController::class, 'incidentes'])->name('conducta.index');
        Route::post('conducta', [RegistroController::class, 'registrarIncidente'])->name('conducta.store');

        /*
        |-----------------------------------------------------------------------
        | Módulo 5: administración
        |-----------------------------------------------------------------------
        */
        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::get('niveles', [CatalogoController::class, 'niveles'])->name('niveles.index');
            Route::post('niveles', [CatalogoController::class, 'guardarNivel'])->name('niveles.store');

            Route::get('cursos', [CatalogoController::class, 'cursos'])->name('cursos.index');
            Route::post('cursos', [CatalogoController::class, 'guardarCurso'])->name('cursos.store');

            Route::get('profesionales', [CatalogoController::class, 'profesionales'])->name('profesionales.index');
            Route::post('profesionales', [CatalogoController::class, 'guardarProfesional'])->name('profesionales.store');

            Route::get('plantillas', [CatalogoController::class, 'plantillas'])->name('plantillas.index');
            Route::post('plantillas', [CatalogoController::class, 'guardarPlantilla'])->name('plantillas.store');

            Route::get('apoderados', [CatalogoController::class, 'apoderados'])->name('apoderados.index');
            Route::post('apoderados', [CatalogoController::class, 'guardarApoderado'])->name('apoderados.store');

            Route::get('consentimientos', [CatalogoController::class, 'consentimientos'])->name('consentimientos.index');
            Route::post('consentimientos', [CatalogoController::class, 'guardarConsentimiento'])->name('consentimientos.store');
            Route::post('consentimientos/{consentimiento}/revocar', [CatalogoController::class, 'revocarConsentimiento'])
                ->name('consentimientos.revocar');
        });
    });
});