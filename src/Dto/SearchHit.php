<?php

namespace App\Dto;

final readonly class SearchHit
{
    public function __construct(
        public string $title,
        public ?string $subtitle,
        public string $url,
    ) {
    }
}
