<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\EstadoAsistencia;
use App\Enums\EstadoComunicacion;
use App\Enums\GravedadIncidente;
use App\Enums\RolUsuario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reglas de negocio puras: enums de estados y catálogo de roles.
 *
 * No tocan la base de datos, así que corren rápido y sin PostgreSQL. Son la
 * primera capa de pruebas que conviene correr cuando algo falla, porque si un
 * enum anda mal el problema NO está en el aislamiento ni en las Policies.
 *
 * ADVERTENCIA: escritas y NO ejecutadas. Ver docs/10.
 */
class ReglasDeNegocioTest extends TestCase
{
    // ------------------------------------------------------------------
    // Roles
    // ------------------------------------------------------------------

    public function test_un_apoderado_no_puede_registrar_asistencia_ni_conducta(): void
    {
        $this->assertFalse(RolUsuario::Apoderado->registraAsistencia());
        $this->assertFalse(RolUsuario::Apoderado->registraConducta());
        $this->assertFalse(RolUsuario::Apoderado->enviaComunicaciones());
        $this->assertFalse(RolUsuario::Apoderado->administra());
    }

    public function test_el_maestro_de_comunicaciones_no_lee_conducta_ni_es_docente(): void
    {
        // Es el rol que la ley obliga a mantener acotado: redacta avisos, no
        // lee lo que hay detrás.
        $this->assertTrue(RolUsuario::Comunicaciones->enviaComunicaciones());
        $this->assertFalse(RolUsuario::Comunicaciones->leeConducta());
        $this->assertFalse(RolUsuario::Comunicaciones->registraAsistencia());
        $this->assertFalse(RolUsuario::Comunicaciones->registraConducta());
    }

    public function test_solo_el_docente_registra_asistencia(): void
    {
        $this->assertTrue(RolUsuario::Docente->registraAsistencia());

        foreach ([
            RolUsuario::Direccion,
            RolUsuario::Administracion,
            RolUsuario::Comunicaciones,
            RolUsuario::Orientacion,
            RolUsuario::Apoderado,
        ] as $rol) {
            $this->assertFalse(
                $rol->registraAsistencia(),
                $rol->value . ' no debería registrar asistencia: quien marca presencia es el que está en la sala.'
            );
        }
    }

    public function test_administrar_es_solo_de_direccion_y_administracion(): void
    {
        $this->assertTrue(RolUsuario::Direccion->administra());
        $this->assertTrue(RolUsuario::Administracion->administra());

        $this->assertFalse(RolUsuario::Docente->administra());
        $this->assertFalse(RolUsuario::Comunicaciones->administra());
        $this->assertFalse(RolUsuario::Orientacion->administra());
        $this->assertFalse(RolUsuario::Apoderado->administra());
    }

    public function test_lectura_de_conducta_usa_los_valores_por_defecto(): void
    {
        // La configuración real se aplica en la aplicación. Acá se comprueba
        // el valor por defecto del enum cuando no hay configuración cargada.
        $configuracion = function_exists('config')
            ? config('colegio.roles_que_leen_conducta')
            : null;

        $roles = is_array($configuracion) && $configuracion !== []
            ? $configuracion
            : ['direccion', 'orientacion', 'docente'];

        foreach ($roles as $valor) {
            $this->assertTrue(
                RolUsuario::from($valor)->leeConducta(),
                $valor . ' está en la lista configurada y debería poder leer conducta.'
            );
        }

        $this->assertNotContains('comunicaciones', $roles);
        $this->assertNotContains('apoderado', $roles);
    }

    // ------------------------------------------------------------------
    // Asistencia
    // ------------------------------------------------------------------

    public function test_una_ausencia_justificada_no_cuenta_como_presente(): void
    {
        // Si "justificado" contara como presente, el porcentaje de asistencia
        // de un alumno con justificativos médicos subiría sin que haya
        // venido nunca.
        $this->assertTrue(EstadoAsistencia::Presente->cuentaComoPresente());
        $this->assertTrue(EstadoAsistencia::Tarde->cuentaComoPresente());

        $this->assertFalse(EstadoAsistencia::Justificado->cuentaComoPresente());
        $this->assertFalse(EstadoAsistencia::Ausente->cuentaComoPresente());
    }

    public function test_justificado_es_una_ausencia_y_exige_explicacion(): void
    {
        $this->assertTrue(EstadoAsistencia::Justificado->esAusencia());
        $this->assertTrue(EstadoAsistencia::Justificado->exigeJustificacion());

        $this->assertFalse(EstadoAsistencia::Ausente->exigeJustificacion());
        $this->assertFalse(EstadoAsistencia::Presente->esAusencia());
    }

    // ------------------------------------------------------------------
    // Conducta
    // ------------------------------------------------------------------

    public function test_la_gravedad_define_si_hay_que_comunicar_y_entrevistar(): void
    {
        $this->assertFalse(GravedadIncidente::Leve->exigeComunicacion());

        $this->assertTrue(GravedadIncidente::Media->exigeComunicacion());
        $this->assertFalse(GravedadIncidente::Media->exigeEntrevista());

        $this->assertTrue(GravedadIncidente::Grave->exigeComunicacion());
        $this->assertTrue(GravedadIncidente::Grave->exigeEntrevista());
    }

    // ------------------------------------------------------------------
    // Comunicaciones
    // ------------------------------------------------------------------

    public function test_una_comunicacion_enviada_es_terminal(): void
    {
        $this->assertTrue(EstadoComunicacion::Enviada->esFinal());
        $this->assertFalse(EstadoComunicacion::Borrador->esFinal());
    }
}