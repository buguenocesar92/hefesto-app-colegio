<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\ContextoTenant;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comprueba que el aislamiento entre colegios está realmente puesto.
 *
 * POR QUÉ ESTE COMANDO EXISTE
 * --------------------------
 * El aislamiento no es solo el código que escribimos: es el estado en que
 * quedó la base de datos. Un despliegue bien intentionado pero mal
 * configurado (un rol con BYPASSRLS, una política borrada a mano, una tabla
 * nueva sin proteger) deja la aplicación funcionando y, al mismo tiempo,
 * completamente abierta entre colegios. Nada en la interfaz lo delata.
 *
 * * Por eso este comando se ejecuta en el despliegue. Devuelve código de
 * salida 1 si algo falla.
 *
 *   php artisan colegio:verificar-aislamiento
 *
 * ADVERTENCIA HONESTA: este comando NO se ejecutó. No había PostgreSQL
 * disponible en el entorno donde se escribió el código. Ver docs/10.
 */
class VerificarAislamientoCommand extends Command
{
    /**
     * Debe coincidir con la lista de la migración
     * `2026_10_17_000018_activar_row_level_security.php`.
     *
     * @var list<string>
     */
    private array $tablasProtegidas = [
        'niveles',
        'cursos',
        'curso_profesional',
        'profesionales',
        'apoderados',
        'alumnos',
        'apoderado_alumnos',
        'matriculas',
        'contactos_emergencia',
        'consentimientos',
        'usuarios',
        'plantillas_comunicacion',
        'comunicaciones',
        'comunicacion_destinatarios',
        'comunicacion_recibos',
        'entrevistas',
        'actas_reunion',
        'asistencias',
        'conducta_incidentes',
        'bitacora_accesos',
        'contadores_folio',
    ];

    protected $signature = 'colegio:verificar-aislamiento';

    protected $description = 'Comprueba que la RLS, el rol de la base de datos y el contexto de colegio protegen los datos de cada colegio.';

    public function handle(): int
    {
        $fallos = [];

        $fallos = array_merge($fallos, $this->revisarRol());
        $fallos = array_merge($fallos, $this->revisarTablas());
        $fallos = array_merge($fallos, $this->revisarFuncionDeContexto());
        $fallos = array_merge($fallos, $this->revisarLecturaAislada());
        $fallos = array_merge($fallos, $this->revisarVistaPublica());

        if ($fallos !== []) {
            $this->error('El aislamiento entre colegios NO está bien puesto:');

            foreach ($fallos as $fallo) {
                $this->line('  - ' . $fallo);
            }

            $this->line('');
            $this->line('No sigas con el despliegue. Revisa la migración');
            $this->line('database/migrations/2026_10_17_000018_activar_row_level_security.php');

            return self::FAILURE;
        }

        $this->info('Aislamiento entre colegios verificado correctamente.');

        return self::SUCCESS;
    }

    /**
     * El rol de la aplicación no puede ser superusuario ni tener BYPASSRLS.
     *
     * Con cualquiera de las dos cosas, PostgreSQL ignora las políticas RLS por
     * completo: las consultas devolverían filas de todos los colegios y la
     * aplicación no tendría cómo enterarse.
     *
     * @return list<string>
     */
    private function revisarRol(): array
    {
        $fila = DB::selectOne(
            'SELECT r.rolname AS nombre, r.rolsuper AS superusuario, r.rolbypassrls AS salta_rls
             FROM pg_roles r
             WHERE r.rolname = current_user'
        );

        if ($fila === null) {
            return ['No se pudo identificar el rol de la aplicación en PostgreSQL.'];
        }

        $fallos = [];

        if ((bool) $fila->superusuario) {
            $fallos[] = sprintf(
                'El rol "%s" es SUPERUSUARIO: ignora todas las políticas RLS.',
                $fila->nombre
            );
        }

        if ((bool) $fila->salta_rls) {
            $fallos[] = sprintf(
                'El rol "%s" tiene BYPASSRLS: ignora todas las políticas RLS.',
                $fila->nombre
            );
        }

        if ($fallos === []) {
            $this->line('  [ok] Rol "' . $fila->nombre . '": sin superusuario y sin BYPASSRLS.');
        }

        return $fallos;
    }

    /**
     * Cada tabla de negocio tiene RLS activada, forzada y con política.
     *
     * @return list<string>
     */
    private function revisarTablas(): array
    {
        $fallos = [];

        foreach ($this->tablasProtegidas as $tabla) {
            $estado = DB::selectOne(
                'SELECT c.relrowsecurity AS rls,
                        c.relforcerowsecurity AS forzada
                 FROM pg_class c
                 WHERE c.oid = to_regclass(?)',
                ['public.' . $tabla]
            );

            if ($estado === null) {
                $fallos[] = 'La tabla public.' . $tabla . ' no existe. Faltó correr las migraciones.';

                continue;
            }

            if (! $estado->rls) {
                $fallos[] = 'La tabla public.' . $tabla . ' NO tiene Row Level Security activada.';

                continue;
            }

            if (! $estado->forzada) {
                $fallos[] = 'La tabla public.' . $tabla . ' tiene RLS pero NO está forzada '
                    . '(FORCE ROW LEVEL SECURITY): el dueño de la tabla saltaría la política.';

                continue;
            }

            $politicas = DB::selectOne(
                'SELECT count(*) AS total
                 FROM pg_policies
                 WHERE schemaname = ? AND tablename = ?',
                ['public', $tabla]
            );

            if ((int) ($politicas->total ?? 0) === 0) {
                $fallos[] = 'La tabla public.' . $tabla . ' tiene RLS activada pero no tiene ninguna política.';
            }
        }

        if ($fallos === []) {
            $this->line('  [ok] ' . count($this->tablasProtegidas) . ' tablas con RLS activada, forzada y con política.');
        }

        return $fallos;
    }

    /**
     * Sin contexto de colegio, la función de contexto tiene que FALLAR.
     *
     * Si devolviera NULL o una cadena vacía en vez de lanzar excepción, una
     * consulta sin colegio devolvería cero filas por accidente en vez de por
     * diseño, y en el futuro alguien podría "arreglarlo" devolviendo NULL y
     * abrir la puerta sin darse cuenta.
     *
     * @return list<string>
     */
    private function revisarFuncionDeContexto(): array
    {
        try {
            DB::select('SELECT public.app_tenant_id()');
        } catch (QueryException) {
            $this->line('  [ok] public.app_tenant_id() falla sin contexto de colegio (aislamiento cerrado).');

            return [];
        }

        return [
            'public.app_tenant_id() devolvió un valor sin que haya contexto de colegio. '
            . 'Debería lanzar excepción para que el aislamiento falle cerrado.',
        ];
    }

    /**
     * Con un colegio que no existe, las tablas de negocio deben salir vacías.
     *
     * @return list<string>
     */
    private function revisarLecturaAislada(): array
    {
        $colegioInexistente = (string) Str::uuid();
        $contexto = new ContextoTenant();
        $fallos = [];

        try {
            $conteo = $contexto->ejecutarAislado(
                $colegioInexistente,
                fn (): int => DB::table('alumnos')->count()
            );
        } catch (QueryException $excepcion) {
            return ['No se pudo leer "alumnos" con un contexto de colegio: ' . $excepcion->getMessage()];
        }

        if ($conteo !== 0) {
            $fallos[] = 'La tabla "alumnos" devolvió ' . $conteo . ' filas para un colegio inexistente.';
        }

        $contextoResiduo = $contexto->tenantIdEnConexion();

        if ($contextoResiduo !== null) {
            $fallos[] = 'La variable app.tenant_id quedó fijada (' . $contextoResiduo . ') después de la transacción. '
                . 'Si queda colgando, el worker de colas podría leer datos de otro colegio.';
        }

        if ($fallos === []) {
            $this->line('  [ok] Con un colegio inexistente no se lee ninguna fila y el contexto no queda colgando.');
        }

        return $fallos;
    }

    /**
     * La vista pública de colegios debe existir y exponer solo tres columnas.
     *
     * @return list<string>
     */
    private function revisarVistaPublica(): array
    {
        $columnas = DB::select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'vw_colegios_publicos'
             ORDER BY column_name"
        );

        if ($columnas === []) {
            return ['No existe la vista public.vw_colegios_publicos.'];
        }

        $nombres = array_map(static fn ($fila): string => (string) $fila->column_name, $columnas);
        sort($nombres);

        $esperadas = ['activo', 'nombre', 'slug'];
        sort($esperadas);

        if ($nombres !== $esperadas) {
            return ['La vista public.vw_colegios_publicos expone columnas distintas de las tres esperadas ('
                . implode(', ', $nombres) . '). No debe exponer datos de contacto de los colegios.'];
        }

        $this->line('  [ok] La vista pública de colegios expone solo slug, nombre y activo.');

        return [];
    }
}