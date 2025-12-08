<?php

namespace App\Exports\v2;

use App\Models\Order;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

class OrderExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $filters;

    public function __construct(Request $request)
    {
        $this->filters = $request->all();
    }

    public function query()
    {
        $query = Order::query();

        if (!empty($this->filters['customers'])) {
            $query->whereIn('customer_id', $this->filters['customers']);
        }

        if (!empty($this->filters['id'])) {
            $query->where('id', 'like', "%{$this->filters['id']}%");
        }

        if (!empty($this->filters['ids'])) {
            $query->whereIn('id', $this->filters['ids']);
        }

        if (!empty($this->filters['buyerReference'])) {
            $query->where('buyer_reference', 'like', "%{$this->filters['buyerReference']}%");
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['loadDate']['start'])) {
            $query->where('load_date', '>=', date('Y-m-d 00:00:00', strtotime($this->filters['loadDate']['start'])));
        }
        if (!empty($this->filters['loadDate']['end'])) {
            $query->where('load_date', '<=', date('Y-m-d 23:59:59', strtotime($this->filters['loadDate']['end'])));
        }

        if (!empty($this->filters['entryDate']['start'])) {
            $query->where('entry_date', '>=', date('Y-m-d 00:00:00', strtotime($this->filters['entryDate']['start'])));
        }
        if (!empty($this->filters['entryDate']['end'])) {
            $query->where('entry_date', '<=', date('Y-m-d 23:59:59', strtotime($this->filters['entryDate']['end'])));
        }

        if (!empty($this->filters['transports'])) {
            $query->whereIn('transport_id', $this->filters['transports']);
        }

        if (!empty($this->filters['salespeople'])) {
            $query->whereIn('salesperson_id', $this->filters['salespeople']);
        }

        if (!empty($this->filters['palletsState'])) {
            if ($this->filters['palletsState'] == 'stored') {
                $query->whereHas('pallets', function ($q) {
                    $q->where('status', 2);
                });
            } elseif ($this->filters['palletsState'] == 'shipping') {
                $query->whereHas('pallets', function ($q) {
                    $q->where('status', 3);
                });
            }
        }

        if (!empty($this->filters['incoterm'])) {
            $query->where('incoterm_id', $this->filters['incoterm']);
        }

        return $query->orderBy('load_date', 'desc');
    }

    public function map($order): array
    {
        return [
            'Id'           => $order->id,
            'Cliente'      => $order->customer->name,
            'Referencia'   => $order->buyer_reference,
            'Estado'       => $order->status,
            'Fecha Carga'  => date('d/m/Y', strtotime($order->load_date)),
            'Comercial'    => $order->salesperson->name ?? 'N/A',
            'Transporte'   => $order->transport->name ?? 'N/A',
            'Palets'       => $order->numberOfPallets ?? 'N/A',
            'Cajas'        => $order->totalBoxes ?? 'N/A',
            'Incoterm'     => $order->incoterm->code ?? 'N/A',
            'Peso Total'   => number_format($order->totalNetWeight, 2, ',', '.'),
        ];
    }

    public function headings(): array
    {
        return [
            'Id',
            'Cliente',
            'Referencia',
            'Estado',
            'Fecha Carga',
            'Comercial',
            'Transporte',
            'Palets',
            'Cajas',
            'Incoterm',
            'Peso Total',
        ];
    }
}
