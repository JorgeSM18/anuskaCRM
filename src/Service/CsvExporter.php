<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Genera descargas CSV compatibles con Excel en español (BOM UTF-8 + separador ";").
 */
class CsvExporter
{
    /**
     * @param list<string>                               $headers
     * @param iterable<int, list<string|int|float|null>> $rows
     */
    public function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 para que Excel muestre bien los acentos
            fputcsv($out, array_map(self::neutralize(...), $headers), ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::neutralize(...), $row), ';', '"', '');
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    /**
     * Anti CSV/Formula injection: una celda que empieza por = + - @ (o tab/CR) la
     * interpretaría Excel/LibreOffice como fórmula. Se antepone un apóstrofo para
     * forzar que se trate como texto.
     */
    private static function neutralize(string|int|float|null $value): string|int|float|null
    {
        if (\is_string($value) && '' !== $value && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }
}
