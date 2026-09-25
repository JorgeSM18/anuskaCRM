<?php

namespace App\Tests\Unit;

use App\Service\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    private InvoiceCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new InvoiceCalculator();
    }

    public function testStandardVat(): void
    {
        $vat = $this->calc->vatAmount('100.00', '21.00');
        $this->assertSame('21.00', $vat);
        $this->assertSame('121.00', $this->calc->total('100.00', $vat));
    }

    public function testRoundingToCents(): void
    {
        // 99.99 * 21% = 20.9979 -> 21.00 ; total 120.99
        $vat = $this->calc->vatAmount('99.99', '21.00');
        $this->assertSame('21.00', $vat);
        $this->assertSame('120.99', $this->calc->total('99.99', $vat));
    }

    public function testReducedVat(): void
    {
        // 250 * 10% = 25.00 ; total 275.00
        $vat = $this->calc->vatAmount('250.00', '10.00');
        $this->assertSame('25.00', $vat);
        $this->assertSame('275.00', $this->calc->total('250.00', $vat));
    }

    public function testZeroVat(): void
    {
        $vat = $this->calc->vatAmount('80.00', '0.00');
        $this->assertSame('0.00', $vat);
        $this->assertSame('80.00', $this->calc->total('80.00', $vat));
    }
}
