<?php

namespace App\Exports\v2;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class A3ERP2OrdersSalesDeliveryNotesExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    use Exportable;

    protected $orders;

    public function __construct(Collection $orders)
    {
        $this->orders = $orders;
    }

    public function collection()
    {
        $rows = [];

        foreach ($this->orders as $order) {
            // Procesar todos los pedidos, incluso si no tienen código Facilcom
            // Los campos faltantes se mostrarán con "-" y se resaltarán en amarillo
            // Obtener año de 2 dígitos basado en la fecha del pedido
            $year = $order->load_date ? date('y', strtotime($order->load_date)) : date('y');
            $serie = 'P' . $year;

            foreach ($order->productDetails as $productDetail) {
                $rows[] = [
                    'CABSERIE' => $serie,
                    'CABNUMDOC' => $order->id ?: '-',
                    'CABFECHA' => $order->load_date ? date('d/m/Y', strtotime($order->load_date)) : '-',
                    // Usar código Facilcom, mostrar "-" si no existe
                    'CABCODCLI' => ($order->customer && $order->customer->facilcom_code) ? $order->customer->facilcom_code : '-',
                    'CABREFERENCIA' => $order->id ?: '-',
                    // Usar código Facilcom del producto, mostrar "-" si no existe
                    'LINCODART' => ($productDetail['product']['facilcomCode'] ?? null) ?: '-',
                    'LINDESCLIN' => ($productDetail['product']['name'] ?? null) ?: '-',
                    'LINBULTOS' => $productDetail['boxes'] ?? '-',
                    'LINUNIDADES' => $productDetail['netWeight'] ?? '-',
                    'LINPRCMONEDA' => $productDetail['unitPrice'] ?? '-',
                    'LINTIPIVA' => ($productDetail['tax']['name'] ?? null) ?: '-',
                ];
            }
        }

        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'CABSERIE',
            'CABNUMDOC',
            'CABFECHA',
            'CABCODCLI',
            'CABREFERENCIA',
            'LINCODART',
            'LINDESCLIN',
            'LINBULTOS',
            'LINUNIDADES',
            'LINPRCMONEDA',
            'LINTIPIVA'
        ];
    }

    public function map($row): array
    {
        return [
            $row['CABSERIE'],
            $row['CABNUMDOC'],
            $row['CABFECHA'],
            $row['CABCODCLI'],
            $row['CABREFERENCIA'],
            $row['LINCODART'],
            $row['LINDESCLIN'],
            $row['LINBULTOS'],
            $row['LINUNIDADES'],
            $row['LINPRCMONEDA'],
            $row['LINTIPIVA']
        ];
    }

    public function title(): string
    {
        return 'ALBARANESVENTA';
    }

    public function styles(Worksheet $sheet)
    {
        // Obtener el rango de datos
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // Solo negrita para encabezados
        $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true
            ]
        ]);

        // Autoajuste básico de columnas
        foreach (range('A', $highestColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Colorear de amarillo las celdas con datos faltantes ("-")
        for ($row = 2; $row <= $highestRow; $row++) {
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellValue = $sheet->getCell($col . $row)->getValue();
                if ($cellValue === '-') {
                    $sheet->getStyle($col . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                    $sheet->getStyle($col . $row)->getFill()->getStartColor()->setRGB('FFFF00'); // Amarillo
                }
            }
        }

        return [];
    }
}

