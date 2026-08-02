<?php

namespace App\Exports\v2;

use App\Models\OrderPlannedProductDetail;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ActiveOrderPlannedProductsExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function collection()
    {

        return OrderPlannedProductDetail::with(['order.customer', 'product', 'tax'])
            ->whereHas('order', function ($q) {
                $q->where('status', 'pending')
                    ->orWhereDate('load_date', '>=', now());
            })
            ->get();
    }

    public function map($detail): array
    {
        $loadDate = $detail->order->load_date;
        $formattedDate = $loadDate ? \Carbon\Carbon::parse($loadDate)->format('d-m-Y') : 'N/A';

        return [
            $detail->order->id,
            $detail->order->customer->name ?? 'N/A',
            $formattedDate,
            $detail->product->name ?? 'N/A',
            $detail->quantity,
            $detail->boxes,
            number_format($detail->unit_price, 2, ',', '.'),
            $detail->tax->rate.'%' ?? 'N/A',
        ];
    }

    public function headings(): array
    {
        return [
            'Pedido',
            'Cliente',
            'Fecha de carga',
            'Producto',
            'Cantidad prevista',
            'Cajas previstas',
            'Precio unitario',
            'Impuesto',
        ];
    }
}
