<?php

namespace App\Exports\v2;

use App\Models\Box;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BoxesReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected $filters;

    public function __construct(Request $request)
    {
        $this->filters = $request;
    }

    public function collection()
    {
        $query = Box::query();

        // Aplicar filtros siguiendo el patrón del PalletController v2
        $this->applyFiltersToQuery($query);

        // Ordenar por ID descendente
        $query->orderBy('id', 'desc');

        return $query->with([
            'product.article',
            'palletBox.pallet.order.customer',
            'palletBox.pallet.storedPallet.store'
        ])->get();
    }

    private function applyFiltersToQuery($query)
    {
        $filters = $this->filters->all();

        if (isset($filters['filters'])) {
            $filters = $filters['filters']; // Para aceptar filtros anidados
        }

        // Filtro por ID de caja
        if (isset($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        // Filtro por múltiples IDs de cajas
        if (isset($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        // Filtro por nombre del artículo
        if (isset($filters['name'])) {
            $query->whereHas('product', function ($query) use ($filters) {
                $query->whereHas('article', function ($query) use ($filters) {
                    $query->where('name', 'like', '%' . $filters['name'] . '%');
                });
            });
        }

        // Filtro por especies
        if (isset($filters['species'])) {
            $query->whereHas('product', function ($query) use ($filters) {
                $query->whereIn('species_id', $filters['species']);
            });
        }

        // Filtro por lotes
        if (isset($filters['lots'])) {
            $query->whereIn('lot', $filters['lots']);
        }

        // Filtro por productos
        if (isset($filters['products'])) {
            $query->whereIn('article_id', $filters['products']);
        }

        // Filtro por palets
        if (isset($filters['pallets'])) {
            $query->whereHas('palletBox', function ($query) use ($filters) {
                $query->whereIn('pallet_id', $filters['pallets']);
            });
        }

        // Filtro por GS1-128
        if (isset($filters['gs1128'])) {
            $query->whereIn('gs1_128', $filters['gs1128']);
        }

        // Filtro por estado del palet
        if (!empty($filters['state'])) {
            if ($filters['state'] === 'stored') {
                $query->whereHas('palletBox.pallet', function ($query) {
                    $query->where('state_id', 2);
                });
            } elseif ($filters['state'] === 'shipped') {
                $query->whereHas('palletBox.pallet', function ($query) {
                    $query->where('state_id', 3);
                });
            }
        }

        // Filtro por estado del pedido
        if (!empty($filters['orderState'])) {
            if ($filters['orderState'] === 'pending') {
                $query->whereHas('palletBox.pallet.order', function ($query) {
                    $query->where('status', 'pending');
                });
            } elseif ($filters['orderState'] === 'finished') {
                $query->whereHas('palletBox.pallet.order', function ($query) {
                    $query->where('status', 'finished');
                });
            } elseif ($filters['orderState'] === 'without_order') {
                $query->whereDoesntHave('palletBox.pallet.order');
            }
        }

        // Filtro por posición
        if (!empty($filters['position'])) {
            if ($filters['position'] === 'located') {
                $query->whereHas('palletBox.pallet.storedPallet', function ($query) {
                    $query->whereNotNull('position');
                });
            } elseif ($filters['position'] === 'unlocated') {
                $query->whereHas('palletBox.pallet.storedPallet', function ($query) {
                    $query->whereNull('position');
                });
            }
        }

        // Filtro por fechas
        if (!empty($filters['createdAt']['start'])) {
            $startDate = date('Y-m-d 00:00:00', strtotime($filters['createdAt']['start']));
            $query->where('created_at', '>=', $startDate);
        }

        if (!empty($filters['createdAt']['end'])) {
            $endDate = date('Y-m-d 23:59:59', strtotime($filters['createdAt']['end']));
            $query->where('created_at', '<=', $endDate);
        }

        // Filtro por observaciones del palet
        if (!empty($filters['notes'])) {
            $query->whereHas('palletBox.pallet', function ($query) use ($filters) {
                $query->where('observations', 'like', "%{$filters['notes']}%");
            });
        }

        // Filtro por almacenes
        if (!empty($filters['stores'])) {
            $query->whereHas('palletBox.pallet.storedPallet', function ($query) use ($filters) {
                $query->whereIn('store_id', $filters['stores']);
            });
        }

        // Filtro por pedidos
        if (!empty($filters['orders'])) {
            $query->whereHas('palletBox.pallet.order', function ($query) use ($filters) {
                $query->whereIn('id', $filters['orders']);
            });
        }
    }

    public function headings(): array
    {
        return [
            'ID Caja',
            'Artículo',
            'Especie',
            'Lote',
            'Peso Neto (kg)',
            'Peso Bruto (kg)',
            'GS1-128',
            'ID Palet',
            'Estado Palet',
            'Pedido',
            'Cliente',
            'Almacén',
            'Posición',
            'Observaciones',
            'Fecha Creación',
        ];
    }

    public function map($box): array
    {
        return [
            $box->id,
            $box->product->article->name ?? '',
            $box->product->species->name ?? '',
            $box->lot,
            number_format($box->net_weight, 2, ',', '.'),
            number_format($box->gross_weight, 2, ',', '.'),
            $box->gs1_128 ?? '',
            $box->palletBox->pallet->id ?? '',
            $this->getPalletState($box),
            $box->palletBox->pallet->order->id ?? '',
            $box->palletBox->pallet->order->customer->name ?? '',
            $box->palletBox->pallet->storedPallet->store->name ?? '',
            $box->palletBox->pallet->storedPallet->position ?? '',
            $box->palletBox->pallet->observations ?? '',
            $box->created_at->format('d/m/Y H:i:s'),
        ];
    }

    private function getPalletState($box)
    {
        if (!$box->palletBox || !$box->palletBox->pallet) {
            return 'Sin palet';
        }

        $stateId = $box->palletBox->pallet->state_id;
        
        switch ($stateId) {
            case 1:
                return 'Pendiente';
            case 2:
                return 'Almacenado';
            case 3:
                return 'Enviado';
            default:
                return 'Desconocido';
        }
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2EFDA']
                ]
            ],
        ];
    }
} 