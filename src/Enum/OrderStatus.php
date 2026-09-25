<?php

namespace App\Enum;

enum OrderStatus: string
{
    case DRAFT = 'draft';
    case PLACED = 'placed';
    case CONFIRMED = 'confirmed';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::PLACED => 'Realizado',
            self::CONFIRMED => 'Confirmado',
            self::PARTIALLY_RECEIVED => 'Parcialmente recibido',
            self::RECEIVED => 'Recibido',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::DRAFT => 'muted',
            self::PLACED => 'warn',
            self::CONFIRMED, self::PARTIALLY_RECEIVED => 'info',
            self::RECEIVED => 'ok',
            self::CANCELLED => 'danger',
        };
    }

    /** Pedidos que se consideran "abiertos" (ni recibidos ni cancelados). */
    public function isOpen(): bool
    {
        return !in_array($this, [self::RECEIVED, self::CANCELLED], true);
    }
}
