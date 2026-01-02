<?php

namespace App\Exports\v2;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;

class A3ERP2OrdersSalesDeliveryNotesExport implements FromCollection, WithHeadings, WithMapping, WithTitle
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
            // Solo procesar si el cliente tiene código Facilcom
            if ($order->customer && $order->customer->facilcom_code) {
                foreach ($order->productDetails as $productDetail) {
                    $rows[] = [
                        'CABSERIE' => 'P',
                        'CABNUMDOC' => $order->id,
                        'CABFECHA' => date('d/m/Y', strtotime($order->load_date)),
                        // Usar código Facilcom en lugar de A3ERP
                        'CABCODCLI' => $order->customer->facilcom_code,
                        'CABREFERENCIA' => $order->id,
                        // Usar código Facilcom en lugar de A3ERP
                        'LINCODART' => $productDetail['product']['facilcomCode'] ?? '',
                        'LINDESCLIN' => $productDetail['product']['name'] ?? '',
                        'LINBULTOS' => $productDetail['boxes'],
                        'LINUNIDADES' => $productDetail['netWeight'],
                        'LINPRCMONEDA' => $productDetail['unitPrice'],
                        'LINTIPIVA' => $productDetail['tax']['name'] ?? '',
                    ];
                }
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
}

