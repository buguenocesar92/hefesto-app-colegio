<?php

declare(strict_types=1);

use App\Http\Middleware\AuditarAcceso;
use App\Http\Middleware\ResolverTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Punto de arranque de la API del Colegio.
 *
 * Nota sobre el esqueleto: este archivo reemplaza al que genera
 * `composer create-project laravel/laravel`. El resto del esqueleto
 * (artisan, public/index.php, storage, etc.) lo genera Composer.
 * Ver docs/04-INSTALACION.md, paso 2.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/api/v1/salud',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CSRF y sesión se aplican por el grupo `web` de Laravel.
        //
        // La interfaz la sirve Nuxt como BFF, de modo que la cookie de
        // sesión viaja en la misma origen y el token CSRF se reenvía como
        // cabecera `X-XSRF-TOKEN`. Ver docs/07, sección CSRF.
        $middleware->web(append: [
            // El orden importa: `ResolverTenant` va antes que `AuditarAcceso`
            // porque la bitácora escribe en `bitacora_accesos`, que tiene
            // RLS y necesita el contexto del colegio ya fijado. Al revés, el
            // registro fallaría por falta de contexto.
            ResolverTenant::class,
            AuditarAcceso::class,
        ]);

        $middleware->alias([
            'tenant.resuelto' => ResolverTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Un rechazo de autorización no debe revelar si el recurso existe en
        // otro colegio. Se responde 404, no 403: un 403 confirmaría que el
        // identificador existe en algún lado.
        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'mensaje' => 'No encontrado.',
                ], 404);
            }

            return null;
        });
    })
    ->create();