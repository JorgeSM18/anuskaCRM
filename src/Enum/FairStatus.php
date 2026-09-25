<?php

namespace App\Enum;

enum FairStatus: string
{
    case PLANNED = 'planned';
    case CONFIRMED = 'confirmed';
    case DONE = 'done';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Planificada',
            self::CONFIRMED => 'Confirmada',
            self::DONE => 'Realizada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::PLANNED => 'muted',
            self::CONFIRMED => 'info',
            self::DONE => 'ok',
            self::CANCELLED => 'danger',
        };
    }
}
