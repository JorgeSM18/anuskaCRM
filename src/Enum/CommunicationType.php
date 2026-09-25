<?php

namespace App\Enum;

enum CommunicationType: string
{
    case EMAIL = 'email';
    case PHONE = 'phone';
    case WHATSAPP = 'whatsapp';
    case MEETING = 'meeting';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::PHONE => 'Teléfono',
            self::WHATSAPP => 'WhatsApp',
            self::MEETING => 'Reunión',
            self::OTHER => 'Otro',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::EMAIL => '✉️',
            self::PHONE => '📞',
            self::WHATSAPP => '💬',
            self::MEETING => '🤝',
            self::OTHER => '•',
        };
    }
}
