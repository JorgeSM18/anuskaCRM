<?php

namespace App\Enum;

enum AppointmentType: string
{
    case SALES_VISIT = 'sales_visit';
    case COLLECTION_PREVIEW = 'collection_preview';
    case ORDER_MEETING = 'order_meeting';
    case CALL = 'call';
    case VIDEO_CALL = 'video_call';
    case STORE_VISIT = 'store_visit';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SALES_VISIT => 'Visita del comercial',
            self::COLLECTION_PREVIEW => 'Presentación de colección',
            self::ORDER_MEETING => 'Reunión de pedido',
            self::CALL => 'Llamada',
            self::VIDEO_CALL => 'Videollamada',
            self::STORE_VISIT => 'Visita a tienda',
            self::OTHER => 'Otro',
        };
    }
}
