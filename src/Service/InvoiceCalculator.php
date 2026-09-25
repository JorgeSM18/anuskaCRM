<?php

namespace App\Service;

/**
 * Cálculo de IVA con bcmath (sin errores de coma flotante).
 * Los importes son cadenas decimales; los tipos, no negativos.
 */
class InvoiceCalculator
{
    /**
     * Cuota de IVA = base * tipo / 100, redondeada a 2 decimales.
     *
     * @param numeric-string $base
     * @param numeric-string $rate
     *
     * @return numeric-string
     */
    public function vatAmount(string $base, string $rate): string
    {
        $raw = bcmul($base, bcdiv($rate, '100', 8), 8);

        return $this->round2($raw);
    }

    /**
     * Total = base + cuota de IVA.
     *
     * @param numeric-string $base
     * @param numeric-string $vatAmount
     *
     * @return numeric-string
     */
    public function total(string $base, string $vatAmount): string
    {
        return bcadd($base, $vatAmount, 2);
    }

    /**
     * Redondeo a 2 decimales (media hacia arriba; importes no negativos).
     *
     * @param numeric-string $n
     *
     * @return numeric-string
     */
    private function round2(string $n): string
    {
        return bcadd($n, '0.005', 2);
    }
}
