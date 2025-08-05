<?php

namespace App\Exports\v2;

use App\Models\RawMaterialReception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RawMaterialReceptionFacilcomExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
        $query = RawMaterialReception::query();

        // Extraer todos los filtros aplicables del request (igual que BoxesReportExport)
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
        return $query->with([
            'supplier',
            'products.product.article'
        ])->get();
    }

    private function applyFiltersToQuery($query, $filters)
    {
        // Para aceptar filtros anidados (igual que BoxesReportExport)
        if (isset($filters['filters'])) {
            $filters = $filters['filters'];
        }

        // Filtro por ID de recepción
        if (isset($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        // Filtro por múltiples IDs de recepciones
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
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fecha',
            'ID Proveedor',
            'Proveedor',
            'ID Artículo',
            'Artículo',
            'Peso Neto',
            'Precio',
            'Lote',
        ];
    }

    public function map($reception): array
    {
        // Mejorar el manejo de relaciones nulas (igual que BoxesReportExport)
        $supplier = $reception->supplier;
        
        // Verificar que el supplier existe y tiene facil_com_code
        if (!$supplier || !$supplier->facil_com_code) {
            return []; // Retornar array vacío para saltar esta recepción
        }

        $rows = [];

        // Agregar productos regulares
        foreach ($reception->products as $product) {
            $productModel = $product->product;
            $article = $productModel ? $productModel->article : null;
            
            // Verificar que el producto y su artículo existen
            if (!$productModel || !$article) {
                continue; // Saltar productos sin artículo
            }

            $rows[] = [
                $reception->id,
                date('d/m/Y', strtotime($reception->date)),
                $supplier->facil_com_code,
                $supplier->name,
                $productModel->facil_com_code ?? '',
                $article->name,
                $product->net_weight,
                $product->price,
                date('dmY', strtotime($reception->date)),
            ];
        }

        // Caso especial PULPO FRESCO LONJA
        if ($reception->declared_total_amount > 0 && $reception->declared_total_net_weight > 0) {
            $rows[] = [
                $reception->id,
                date('d/m/Y', strtotime($reception->date)),
                $supplier->facil_com_code,
                $supplier->name,
                100,
                'PULPO FRESCO LONJA',
                $reception->declared_total_net_weight * -1,
                $reception->declared_total_amount / $reception->declared_total_net_weight,
                date('dmY', strtotime($reception->date)),
            ];
        }

        return $rows;
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