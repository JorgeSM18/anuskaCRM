<?php

namespace App\Enum;

enum SupplierStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activo',
            self::INACTIVE => 'Inactivo',
        };
    }

    /** Clase CSS del badge (ver assets/styles/app.scss). */
    public function badge(): string
    {
        return match ($this) {
            self::ACTIVE => 'ok',
            self::INACTIVE => 'muted',
        };
    }
}
