<?php

namespace App\Enum;

enum AppointmentStatus: string
{
    case PENDING = 'pending';
    case DONE = 'done';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::DONE => 'Realizada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::PENDING => 'warn',
            self::DONE => 'ok',
            self::CANCELLED => 'muted',
        };
    }
}
