<?php

namespace App\Exports\v2;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;

class A3ERPOrdersSalesDeliveryNotesExport implements FromCollection, WithHeadings, WithMapping, WithTitle
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
            foreach ($order->productDetails as $productDetail) {
                $rows[] = [
                    'CABSERIE' => 'P',
                    'CABNUMDOC' => $order->id,
                    'CABFECHA' => date('d/m/Y', strtotime($order->load_date)),
                    'CABCODCLI' => optional($order->customer)->a3erp_code,
                    'CABREFERENCIA' => $order->id,
                    'LINCODART' => $productDetail['product']['a3erpCode'] ?? '',
                    'LINDESCLIN' => $productDetail['product']['name'] ?? '',
                    'LINBULTOS' => $productDetail['boxes'],
                    'LINUNIDADES' => $productDetail['netWeight'],
                    'LINPRCMONEDA' => $productDetail['unitPrice'],
                    'LINTIPIVA' => $productDetail['tax']['name'] ?? '',
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
}
