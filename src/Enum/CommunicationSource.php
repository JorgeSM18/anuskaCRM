<?php

namespace App\Enum;

/**
 * Origen de una comunicación. Por ahora solo MANUAL; queda preparado para
 * futuras integraciones de correo (GMAIL, IMAP…) sin migrar el esquema.
 */
enum CommunicationSource: string
{
    case MANUAL = 'manual';
}
