<?php

declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |-----------------------------------------------------------------------
    | Canales
    |-----------------------------------------------------------------------
    |
    | `colegio_seguridad` existe por una razón técnica, no de estilo.
    |
    | Los accesos rechazados ANTES de iniciar sesión no tienen colegio: el
    | intento contra un dominio que no existe, la cuenta desactivada. La
    | tabla `bitacora_accesos` exige `tenant_id` (no hay hechos sin colegio),
    | así que ahí no se pueden escribir.
    |
    | Esos rechazos van a un canal aparte, en JSON, para poder revisarlos sin
    | mezclarlos con los errores de la aplicación.
    |
    | CUIDADO CON LOS DATOS PERSONALES EN ESTE CANAL
    | -----------------------------------------------
    | Este canal escribe correos y dominios. Es útil para investigar, pero es
    | también un registro de datos personales: entra en la política de
    | retención del colegio. Ver docs/08.
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'daily')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => (int) env('LOG_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'colegio_seguridad' => [
            'driver' => 'monolog',
            'level' => 'info',
            'handler' => StreamHandler::class,
            'formatter' => JsonFormatter::class,
            'with' => [
                'stream' => env('LOG_SEGURIDAD_PATH', 'php://stderr'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];