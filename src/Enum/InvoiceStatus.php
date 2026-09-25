<?php

namespace App\Enum;

enum InvoiceStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::PAID => 'Pagada',
            self::CANCELLED => 'Anulada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::PENDING => 'warn',
            self::PAID => 'ok',
            self::CANCELLED => 'muted',
        };
    }
}
