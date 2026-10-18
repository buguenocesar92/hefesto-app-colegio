<?php

declare(strict_types=1);

use App\Models\BitacoraAcceso;
use Illuminate\Support\Facades\Schedule;

/*
|-------------------------------------------------------------------------------
| Consola de artisan
|-------------------------------------------------------------------------------
|
| Laravel descubre solo los comandos que están en `app/Console/Commands`, así
| que `colegio:verificar-aislamiento` y `colegio:crear` no se registran acá.
|
| Lo que sí vive acá es la programación de tareas, y en concreto una cosa que
| conviene mirar: la retención de la bitácora de accesos.
|
| RETENCIÓN DE LA BITÁCORA
| ------------------------
| Por defecto se conserva un año (8760 horas) y se recorta una vez al día.
| La retención real la decide el colegio, porque depende de su política de
| tratamiento de datos: ver docs/08, sección "Lo que NO se hizo".
|
| Este recorte es reversible en los hechos: los datos que se borran de la
| bitácora no se recuperan. Por eso el valor por defecto es generoso.
|
*/

Schedule::command('model:prune', [
    '--model' => [BitacoraAcceso::class],
    '--hours' => (int) config('colegio.retencion_bitacora_horas', 8760),
])->dailyAt('03:00')->description('Recorta la bitácora de accesos según la retención configurada.');