<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canal por el que sale una comunicación.
 *
 * "whatsapp" es un canal de primera clase, no un alias de correo. Es el
 * canal que el colegio usa hoy, y el motivo por el que este sistema existe.
 *
 * LA DIFERENCIA CON EL ENVÍO REAL
 * ------------------------------
 * Registrar una comunicación por WhatsApp NO envía el mensaje. Este sistema
 * registra lo que el colegio YA envió por el canal que ya usa, y deja
 * constancia. Enviar de verdad por WhatsApp requiere un contrato con un
 * proveedor (que además exige consentimiento del titular) y es un módulo
 * aparte, no implementado. Ver docs/09, alcance.
 */
enum CanalComunicacion: string
{
    case Correo = 'correo';
    case Whatsapp = 'whatsapp';
    case Presencial = 'presencial';
    case Telefono = 'telefono';
    case Web = 'web';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Correo => 'Correo',
            self::Whatsapp => 'WhatsApp',
            self::Presencial => 'Presencial',
            self::Telefono => 'Teléfono',
            self::Web => 'Portal web',
        };
    }

    /**
     * ¿El canal permite obtener un acuse de recibo automático?
     *
     * Solo el portal web: los demás dependen de que alguien lo anote. Por
     * eso un aviso por WhatsApp con `requiere_acuse` se queda esperando
     * que alguien registre el acuse, y eso es correcto.
     */
    public function tieneAcuseAutomatico(): bool
    {
        return $this === self::Web;
    }
}