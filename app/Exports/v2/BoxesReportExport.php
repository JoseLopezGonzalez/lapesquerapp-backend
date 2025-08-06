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
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class BoxesReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected $filters;
    protected $limit;

    public function __construct(Request $request, $limit = null)
    {
        $this->filters = $request;
        $this->limit = $limit;
    }

    public function collection()
    {
        $query = Box::query();

        // Extraer todos los filtros aplicables del request (igual que PalletController)
        $filters = $this->filters->all();

        // Aplicar filtros si existen
        if ($filters) {
            $this->applyFiltersToQuery($query, $filters);
        }

        // Ordenar por ID descendente
        $query->orderBy('id', 'desc');

        // Aplicar límite si se especifica (útil para testing)
        if ($this->limit) {
            $query->limit($this->limit);
        }

        // Cargar relaciones de forma más eficiente
        return $query->with([
            'product.article',
            'product.species',
            'palletBox.pallet.order.customer',
            'palletBox.pallet.storedPallet.store'
        ])->get();
    }

    private function applyFiltersToQuery($query, $filters)
    {
        // Para aceptar filtros anidados (igual que PalletController)
        if (isset($filters['filters'])) {
            $filters = $filters['filters'];
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
        if (!empty($filters['palletState'])) {
            if ($filters['palletState'] === 'stored') {
                $query->whereHas('palletBox.pallet', function ($query) {
                    $query->where('state_id', 2);
                });
            } elseif ($filters['palletState'] === 'shipped') {
                $query->whereHas('palletBox.pallet', function ($query) {
                    $query->where('state_id', 3);
                });
            }
        }

        // Filtro por estado del pedido (actualizado para múltiples valores)
        if (!empty($filters['orderState'])) {
            $orderStates = is_array($filters['orderState']) ? $filters['orderState'] : [$filters['orderState']];
            
            // Manejar el caso especial de 'without_order'
            if (in_array('without_order', $orderStates)) {
                $orderStates = array_filter($orderStates, function($state) {
                    return $state !== 'without_order';
                });
                
                if (!empty($orderStates)) {
                    // Si hay otros estados además de 'without_order', usar OR
                    $query->where(function($query) use ($orderStates) {
                        $query->whereHas('palletBox.pallet.order', function ($query) use ($orderStates) {
                            $query->whereIn('status', $orderStates);
                        })->orWhereHas('palletBox.pallet', function ($query) {
                            $query->whereDoesntHave('order');
                        });
                    });
                } else {
                    // Solo 'without_order'
                    $query->whereHas('palletBox.pallet', function ($query) {
                        $query->whereDoesntHave('order');
                    });
                }
            } else {
                // Solo estados normales
                $query->whereHas('palletBox.pallet.order', function ($query) use ($orderStates) {
                    $query->whereIn('status', $orderStates);
                });
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

        // Filtro por IDs de pedidos específicos
        if (!empty($filters['orderIds'])) {
            $orderIds = is_array($filters['orderIds']) ? $filters['orderIds'] : explode(',', $filters['orderIds']);
            $query->whereHas('palletBox.pallet.order', function ($query) use ($orderIds) {
                $query->whereIn('id', $orderIds);
            });
        }

        // Filtro por fechas de pedidos
        if (!empty($filters['orderDates'])) {
            if (isset($filters['orderDates']['start'])) {
                $startDate = date('Y-m-d 00:00:00', strtotime($filters['orderDates']['start']));
                $query->whereHas('palletBox.pallet.order', function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                });
            }
            if (isset($filters['orderDates']['end'])) {
                $endDate = date('Y-m-d 23:59:59', strtotime($filters['orderDates']['end']));
                $query->whereHas('palletBox.pallet.order', function ($query) use ($endDate) {
                    $query->where('created_at', '<=', $endDate);
                });
            }
        }

        // Filtro por referencia de compra
        if (!empty($filters['orderBuyerReference'])) {
            $query->whereHas('palletBox.pallet.order', function ($query) use ($filters) {
                $query->where('buyer_reference', 'like', '%' . $filters['orderBuyerReference'] . '%');
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
        // Mejorar el manejo de relaciones nulas
        $product = $box->product;
        $article = $product ? $product->article : null;
        $species = $product ? $product->species : null;
        
        $palletBox = $box->palletBox;
        $pallet = $palletBox ? $palletBox->pallet : null;
        $order = $pallet ? $pallet->order : null;
        $customer = $order ? $order->customer : null;
        $storedPallet = $pallet ? $pallet->storedPallet : null;
        $store = $storedPallet ? $storedPallet->store : null;

        return [
            $box->id,
            $article ? $article->name : '-',
            $species ? $species->name : '-',
            $box->lot ?? '-',
            $box->net_weight ?? 0,
            $box->gross_weight ?? 0,
            $box->gs1_128 ?? '-',
            $pallet ? $pallet->id : '-',
            $this->getPalletState($pallet),
            $order ? $order->id : '-',
            $customer ? $customer->name : '-',
            $store ? $store->name : '-',
            $storedPallet ? $storedPallet->position : '-',
            $pallet ? ($pallet->observations ?? '-') : '-',
            $box->created_at ? $box->created_at : '-',
        ];
    }

    private function getPalletState($pallet)
    {
        if (!$pallet) {
            return '-';
        }

        $stateId = $pallet->state_id;
        
        switch ($stateId) {
            case 1:
                return 'Pendiente';
            case 2:
                return 'Almacenado';
            case 3:
                return 'Enviado';
            default:
                return '-';
        }
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

        // Formato de números para columnas de peso (E y F)
        $sheet->getStyle('E:F')->getNumberFormat()->setFormatCode('#,##0.00');

        // Autoajuste básico de columnas
        foreach (range('A', $highestColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [];
    }


} 