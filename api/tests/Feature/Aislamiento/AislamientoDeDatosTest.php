<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Alumno;
use App\Models\Comunicacion;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PRUEBAS CRÍTICAS: un colegio no puede leer los datos de otro.
 *
 * Estas pruebas van contra PostgreSQL de verdad, porque el aislamiento está
 * implementado con Row Level Security. Si se corrieran contra SQLite,
 * pasarían sin comprobar nada. Ver phpunit.xml.
 *
 * Lo que se comprueba acá:
 *
 *  1. Sin contexto de colegio, las tablas de negocio fallan en vez de
 *     devolver filas.
 *  2. Con el contexto de otro colegio, cada uno ve solo lo suyo.
 *  3. Un identificador de otro colegio no existe para esta persona.
 *  4. No se puede escribir una fila en nombre de otro colegio.
 *  5. El contexto no sobrevive a la transacción.
 *
 * ADVERTENCIA: estas pruebas están ESCRITAS y NO EJECUTADAS. En el entorno
 * donde se escribió el código no había PostgreSQL. Ver docs/10.
 */
class AislamientoDeDatosTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_contexto_de_colegio_la_consulta_falla_en_vez_de_traer_datos(): void
    {
        $colegio = $this->crearColegio('aislamiento-uno.cl');
        Alumno::factory()->create(['tenant_id' => $colegio['colegio']->id]);

        // Se limpia el contexto a propósito: es la situación de una consulta
        // que se escapara del middleware.
        //
        // Lo que se espera NO es "cero filas", sino un error.
        // `app_tenant_id()` lanza excepción cuando no hay colegio y la
        // política RLS aborta la consulta. Devolver cero filas sería más
        // cómodo de probar, pero dejaría pasar el caso peligroso: que
        // alguien "arregle" la función devolviendo NULL y termine leyendo
        // filas del colegio equivocado sin que nada lo delate.
        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, '']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Alumno::query()->count();
    }

    public function test_cada_colegio_solo_ve_sus_propias_filas(): void
    {
        $primero = $this->crearColegio('aislamiento-a.cl');
        Alumno::factory()->count(3)->create(['tenant_id' => $primero['colegio']->id]);

        $segundo = $this->crearColegio('aislamiento-b.cl');
        Alumno::factory()->count(2)->create(['tenant_id' => $segundo['colegio']->id]);

        $this->fijarColegio($primero['colegio']->id);
        $this->assertSame(3, Alumno::query()->count());
        $this->assertSame(
            $primero['colegio']->id,
            Alumno::query()->value('tenant_id')
        );

        $this->fijarColegio($segundo['colegio']->id);
        $this->assertSame(2, Alumno::query()->count());
        $this->assertSame(
            $segundo['colegio']->id,
            Alumno::query()->value('tenant_id')
        );
    }

    public function test_la_comunicacion_de_otro_colegio_no_se_puede_leer_ni_escribir(): void
    {
        $primero = $this->crearColegio('aislamiento-c.cl');
        $propia = Comunicacion::factory()->create(['tenant_id' => $primero['colegio']->id]);

        $segundo = $this->crearColegio('aislamiento-d.cl');
        $ajena = Comunicacion::factory()->create(['tenant_id' => $segundo['colegio']->id]);

        // Se vuelve al contexto del primer colegio.
        $this->fijarColegio($primero['colegio']->id);

        $this->assertNotNull($propia->fresh());
        $this->assertNull(Comunicacion::find($ajena->id));

        // Y tampoco se puede tocar: la política tiene WITH CHECK.
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('comunicaciones')
            ->where('id', $ajena->id)
            ->update(['titulo' => 'secuestrada']);
    }

    public function test_no_se_puede_insertar_una_fila_con_el_colegio_de_otro(): void
    {
        $primero = $this->crearColegio('aislamiento-e.cl');
        $segundo = $this->crearColegio('aislamiento-f.cl');

        $usuario = $primero['comunicaciones'];

        // Contexto del primer colegio, pero se intenta guardar una
        // comunicación del segundo. La política WITH CHECK lo tiene que
        // impedir.
        $this->fijarColegio($primero['colegio']->id);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Comunicacion::query()->create([
            'tenant_id' => $segundo['colegio']->id,
            'referencia' => 'COM-2026-999999',
            'titulo' => 'Comunicación con colegio ajeno',
            'cuerpo' => 'No debería poder guardarse nunca.',
            'canal' => 'correo',
            'tipo_destinatario' => 'curso',
            'remitente_usuario_id' => $usuario->id,
            'estado' => 'enviada',
        ]);
    }

    public function test_el_contexto_de_colegio_no_sobrevive_a_la_transaccion(): void
    {
        $colegio = $this->crearColegio('aislamiento-g.cl');
        Alumno::factory()->create(['tenant_id' => $colegio['colegio']->id]);

        $contexto = new ContextoTenant();

        $conteo = $contexto->ejecutarAislado(
            $colegio['colegio']->id,
            fn (): int => Alumno::query()->count()
        );

        $this->assertSame(1, $conteo);
        $this->assertNull(
            $contexto->tenantIdEnConexion(),
            'La variable de contexto debe desaparecer al terminar la transacción. '
            . 'Si queda, el worker de colas podría leer datos de otro colegio.'
        );
    }

    public function test_la_funcion_de_contexto_falla_cerrada_sin_colegio(): void
    {
        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, '']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::select('SELECT public.app_tenant_id()');
    }

    public function test_la_tabla_colegios_no_tiene_rls_por_diseno(): void
    {
        // Esta es una excepción consciente, documentada en docs/03, sección 4.
        // La prueba fija el comportamiento para que, si alguien cambia la
        // migración, quede claro que cambió a propósito.
        $estado = DB::selectOne(
            "SELECT relrowsecurity AS rls
             FROM pg_class
             WHERE oid = to_regclass('public.colegios')"
        );

        $this->assertFalse((bool) ($estado->rls ?? true));
    }

    public function test_la_vista_publica_solo_expone_tres_columnas(): void
    {
        $columnas = DB::select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'vw_colegios_publicos'
             ORDER BY column_name"
        );

        $nombres = array_map(static fn ($fila): string => (string) $fila->column_name, $columnas);

        $this->assertSame(['activo', 'nombre', 'slug'], $nombres);
    }
}