<?php

namespace App\Exports\v2;

use App\Models\CeboDispatch;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CeboDispatchFacilcomExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected $filters;
    protected $limit;
    protected $index;

    public function __construct(Request $request, $limit = null)
    {
        $this->filters = $request;
        $this->limit = $limit;
        $this->index = 1;
    }

    public function collection()
    {
        $query = CeboDispatch::query();

        // Extraer todos los filtros aplicables del request
        $filters = $this->filters->all();

        // Aplicar filtros si existen
        if ($filters) {
            $this->applyFiltersToQuery($query, $filters);
        }

        // Ordenar por fecha descendente
        $query->orderBy('date', 'desc');

        // Aplicar límite si se especifica (útil para testing)
        if ($this->limit) {
            $query->limit($this->limit);
        }

        // Cargar relaciones de forma más eficiente
        $dispatches = $query->with([
            'supplier',
            'products.product.article'
        ])->get();

        // Crear una colección plana con un elemento por producto
        $flatCollection = collect();
        
        foreach ($dispatches as $dispatch) {
            $supplier = $dispatch->supplier;
            
            // Verificar que el supplier existe y tiene facilcom_cebo_code
            if (!$supplier || !$supplier->facilcom_cebo_code) {
                continue; // Saltar este despacho
            }

            // Solo procesar si el tipo de exportación es facilcom
            if ($dispatch->export_type == 'facilcom') {
                foreach ($dispatch->products as $product) {
                    $productModel = $product->product;
                    $article = $productModel ? $productModel->article : null;
                    
                    // Verificar que el producto y su artículo existen
                    if (!$productModel || !$article) {
                        continue; // Saltar productos sin artículo
                    }

                    // Crear un objeto con los datos necesarios
                    $flatCollection->push((object) [
                        'dispatch' => $dispatch,
                        'product' => $product,
                        'productModel' => $productModel,
                        'article' => $article,
                        'supplier' => $supplier,
                        'index' => $this->index
                    ]);
                }

                // Incrementar el índice solo después de procesar todo el despacho
                $this->index++;
            }
        }

        return $flatCollection;
    }

    private function applyFiltersToQuery($query, $filters)
    {
        // Para aceptar filtros anidados
        if (isset($filters['filters'])) {
            $filters = $filters['filters'];
        }

        // Filtro por ID de despacho
        if (isset($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        // Filtro por múltiples IDs de despachos
        if (isset($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        // Filtro por proveedores
        if (isset($filters['suppliers'])) {
            $query->whereIn('supplier_id', $filters['suppliers']);
        }

        // Filtro por fechas
        if (isset($filters['dates'])) {
            $dates = $filters['dates'];
            if (isset($dates['start'])) {
                $startDate = date('Y-m-d 00:00:00', strtotime($dates['start']));
                $query->where('date', '>=', $startDate);
            }
            if (isset($dates['end'])) {
                $endDate = date('Y-m-d 23:59:59', strtotime($dates['end']));
                $query->where('date', '<=', $endDate);
            }
        }

        // Filtro por especies
        if (isset($filters['species'])) {
            $query->whereHas('products.product', function ($query) use ($filters) {
                $query->whereIn('species_id', $filters['species']);
            });
        }

        // Filtro por productos
        if (isset($filters['products'])) {
            $query->whereHas('products.product', function ($query) use ($filters) {
                $query->whereIn('id', $filters['products']);
            });
        }

        // Filtro por notas
        if (isset($filters['notes'])) {
            $query->where('notes', 'like', '%' . $filters['notes'] . '%');
        }

        // Filtro por tipo de exportación
        if (isset($filters['export_type'])) {
            $query->where('export_type', $filters['export_type']);
        }
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

    public function map($item): array
    {
        return [
            $item->index, // Mismo código para todo el despacho
            date('d/m/Y', strtotime($item->dispatch->date)),
            $item->supplier->facilcom_cebo_code,
            $item->supplier->name,
            $item->productModel->facil_com_code ?? '',
            $item->article->name,
            $item->product->net_weight,
            $item->product->price,
            date('dmY', strtotime($item->dispatch->date)),
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

        // Formato de números para columnas de peso y precio (G y H)
        $sheet->getStyle('G:H')->getNumberFormat()->setFormatCode('#,##0.00');

        // Autoajuste básico de columnas
        foreach (range('A', $highestColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [];
    }
} 