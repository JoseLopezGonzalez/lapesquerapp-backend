<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class PdfExtractionService
{
    public function extractAndProcess(UploadedFile $file): array
    {
        $path = $file->store('pdfs');
        $fullPath = Storage::path($path);

        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($fullPath);
            $text = $pdf->getText();
        } finally {
            Storage::delete($path);
        }

        return $this->processPdfText($text);
    }

    /**
     * Procesa el texto extraído del PDF y lo convierte en JSON estructurado.
     * Lógica específica para documentos de compras (Compras, buyer, company, purchases, etc.).
     */
    public function processPdfText(string $text): array
    {
        $text = str_replace("\t", ' ', $text);
        $text = str_replace("\r", '');
        $lines = explode("\n", $text);

        $cleanedLines = [];
        $i = 0;
        while ($i < count($lines)) {
            $line = trim($lines[$i] ?? '');
            if (
                isset($lines[$i + 1]) &&
                ! $this->startsWithDigit(trim($lines[$i + 1])) &&
                ! $this->isLikelyNewSection($lines[$i + 1])
            ) {
                $line .= ' ' . trim($lines[$i + 1]);
                $i += 2;
            } else {
                $i++;
            }
            $line = preg_replace('/\s{2,}/', ' ', $line);
            if ($line !== '') {
                $cleanedLines[] = $line;
            }
        }

        $jsonData = [
            'buyer' => '',
            'company' => '',
            'date' => '',
            'purchases' => [],
            'services' => [],
            'totals' => [],
        ];

        for ($i = 0; $i < count($cleanedLines); $i++) {
            $line = $cleanedLines[$i];

            if (preg_match('/^Comprador:(\S+)/', $line, $m)) {
                $jsonData['buyer'] = $m[1];
                $jsonData['company'] = $cleanedLines[$i + 1] ?? '';
            }
            if (preg_match('/^Fecha:(.+)/', $line, $m)) {
                $jsonData['date'] = trim($m[1]);
            }
            if ($this->isPurchaseLine($line)) {
                $pattern = '/^(?<boxes>\d+)\s+M(?<weight>[\d,\.]+,\d+)\s+(?<product>.+?)\s+(?<price>[\d,\.]+,\d+)\s+(?<total>[\d,\.]+,\d+)\s+(?<seller>.+)$/';
                if (preg_match($pattern, $line, $matches)) {
                    $jsonData['purchases'][] = [
                        'boxes' => $matches['boxes'],
                        'weight' => $matches['weight'],
                        'product' => trim($matches['product']),
                        'pricePerKg' => $matches['price'],
                        'total' => $matches['total'],
                        'seller' => trim($matches['seller']),
                    ];
                }
            }
            if (preg_match('/(TARIFA|CUOTA|SERV\.)/i', $line)) {
                $jsonData['services'][] = ['description' => $line];
            }
            if (str_contains($line, 'Total Pesca')) {
                $jsonData['totals']['totalFishing'] = $cleanedLines[$i + 1] ?? '';
            }
            if (str_contains($line, 'IVA  Pesca')) {
                $jsonData['totals']['ivaFishing'] = $cleanedLines[$i + 1] ?? '';
            }
            if ($line === 'Total') {
                $jsonData['totals']['grandTotal'] = $cleanedLines[$i + 1] ?? '';
            }
        }

        return $jsonData;
    }

    private function isPurchaseLine(string $line): bool
    {
        return preg_match('/^\d+\s+M\d/', $line) === 1;
    }

    private function isLikelyNewSection(string $line): bool
    {
        $line = trim($line);

        return str_starts_with($line, 'Fecha:')
            || str_contains($line, 'Total Pesca')
            || str_contains($line, 'Servicios')
            || str_starts_with($line, 'Base% IVA');
    }

    private function startsWithDigit(string $line): bool
    {
        return preg_match('/^\d/', $line) === 1;
    }
}
