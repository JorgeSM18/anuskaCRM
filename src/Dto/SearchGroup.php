<?php

namespace App\Dto;

final readonly class SearchGroup
{
    /**
     * @param list<SearchHit> $hits
     */
    public function __construct(
        public string $label,
        public array $hits,
    ) {
    }
}
