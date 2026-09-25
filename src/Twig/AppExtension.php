<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->money(...)),
        ];
    }

    /**
     * Formatea un importe (decimal en string) como "1.234,56 €".
     * Devuelve "—" si es nulo o vacío.
     */
    public function money(int|float|string|null $amount): string
    {
        if (null === $amount || '' === $amount) {
            return '—';
        }

        return number_format((float) $amount, 2, ',', '.').' €';
    }
}
