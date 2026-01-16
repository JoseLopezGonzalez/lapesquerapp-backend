<?php

namespace App\Exports\v2;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

class ProductLotDetailsExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function collection()
    {
        $rows = [];

        foreach ($this->order->productsWithLotsDetails as $productDetail) {
            foreach ($productDetail['lots'] as $lot) {
                $rows[] = [
                    'Pedido' => $this->order->id,
                    'Cliente' => $this->order->customer->name,
                    'Producto' => $productDetail['product']['name'],
                    'GTIN Caja' => $productDetail['product']['boxGtin'] ?? 'N/A',
                    'Lote' => $lot['lot'],
                    'Cajas' => $lot['boxes'],
                    'Peso Neto' => number_format($lot['netWeight'], 2, ',', '.'),
                ];
            }
        }

        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'Pedido',
            'Cliente',
            'Producto',
            'GTIN Caja',
            'Lote',
            'Cajas',
            'Peso Neto'
        ];
    }

    public function map($row): array
    {
        return [
            $row['Pedido'],
            $row['Cliente'],
            $row['Producto'],
            $row['GTIN Caja'],
            $row['Lote'],
            $row['Cajas'],
            $row['Peso Neto'],
        ];
    }
}
