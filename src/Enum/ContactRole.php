<?php

namespace App\Enum;

enum ContactRole: string
{
    case SALES_REP = 'sales_rep';
    case ADMIN = 'admin';
    case CUSTOMER_SERVICE = 'customer_service';
    case MANAGEMENT = 'management';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SALES_REP => 'Comercial',
            self::ADMIN => 'Administración',
            self::CUSTOMER_SERVICE => 'Atención al cliente',
            self::MANAGEMENT => 'Dirección',
            self::OTHER => 'Otro',
        };
    }

    /**
     * @return array<string, string> value => label, para desplegables
     */
    public static function choices(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->label()] = $case->value;
        }

        return $out;
    }
}
