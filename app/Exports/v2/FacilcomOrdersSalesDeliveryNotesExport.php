<?php

namespace App\Exports\v2;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class FacilcomOrdersSalesDeliveryNotesExport implements FromArray, WithHeadings, WithStyles
{
    use Exportable;

    protected $orders;
    protected $index = 1;

    public function __construct(Collection $orders)
    {
        $this->orders = $orders;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->orders as $order) {
            foreach ($order->productDetails as $productDetail) {
                $rows[] = [
                    $this->index,
                    $order->load_date ? date('d/m/Y', strtotime($order->load_date)) : '-',
                    // Mostrar "-" si el cliente no tiene código Facilcom
                    ($order->customer['facilcom_code'] ?? null) ?: '-',
                    ($order->customer['name'] ?? null) ?: '-',
                    // Mostrar "-" si el producto no tiene código Facilcom
                    ($productDetail['product']['facilcomCode'] ?? null) ?: '-',
                    ($productDetail['product']['name'] ?? null) ?: '-',
                    $productDetail['netWeight'] ?? '-',
                    $productDetail['unitPrice'] ?? '-',
                    $order->load_date ? date('dmY', strtotime($order->load_date)) : '-',
                ];
            }

            // Línea resumen "PEDIDO #"
            $rows[] = [
                $this->index,
                $order->load_date ? date('d/m/Y', strtotime($order->load_date)) : '-',
                // Mostrar "-" si el cliente no tiene código Facilcom
                ($order->customer['facilcom_code'] ?? null) ?: '-',
                ($order->customer['name'] ?? null) ?: '-',
                '106',
                'PEDIDO #' . ($order->id ?? '-'),
                '0',
                '0',
                '-',
            ];

            $this->index++;
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'CODIGO',
            'Fecha',
            'CODIGO CLIENTE',
            'Destino',
            'Cod. Producto',
            'Producto',
            'Cantidad Kg',
            'Precio',
            'Lote asignado',
        ];
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
