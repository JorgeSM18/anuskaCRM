<?php

namespace App\Tests\Unit;

use App\Service\CsvExporter;
use PHPUnit\Framework\TestCase;

class CsvExporterTest extends TestCase
{
    public function testNeutralizesFormulaInjection(): void
    {
        $csv = $this->render(['Nombre'], [['=HYPERLINK("http://malo","x")'], ['Marca normal']]);

        // La celda con fórmula queda prefijada con apóstrofo (texto, no fórmula).
        self::assertStringContainsString("'=HYPERLINK", $csv);
        // Un valor normal no se toca.
        self::assertStringContainsString('Marca normal', $csv);
        self::assertStringNotContainsString("'Marca normal", $csv);
    }

    /**
     * @param list<string>                      $headers
     * @param list<list<string|int|float|null>> $rows
     */
    private function render(array $headers, array $rows): string
    {
        $response = (new CsvExporter())->stream('x.csv', $headers, $rows);
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
