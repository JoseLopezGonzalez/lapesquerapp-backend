<?php

namespace App\Exports\v2;

use App\Models\RawMaterialReception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RawMaterialReceptionA3erpExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
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

        // NOTA: Esta exportación usa FromCollection porque map() retorna múltiples filas por registro
        // (una fila por producto + posible fila adicional para PULPO FRESCO LONJA).
        // FromQuery no soporta map() que retorna múltiples filas, por lo que usamos FromCollection.
        // Para optimizar memoria, usamos eager loading con with() y limitamos con ->limit() si es necesario.
        return $query->with([
            'supplier',
            'products.product.article'
        ])->get();
    }

    private function applyFiltersToQuery($query, $filters)
    {
        // Para aceptar filtros anidados
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
            'CABSERIE',
            'CABNUMDOC',
            'CABFECHA',
            'CABCODPRO',
            'CABREFERENCIA',
            'LINCODART',
            'LINDESCLIN',
            'LINUNIDADES',
            'LINPRCMONEDA',
        ];
    }

    public function map($reception): array
    {
        // Mejorar el manejo de relaciones nulas
        $supplier = $reception->supplier;
        
        $rows = [];

        // Obtener año de 2 dígitos basado en la fecha de recepción
        $year = $reception->date ? date('y', strtotime($reception->date)) : '25';
        $serie = 'RE' . $year;

        // Agregar productos regulares
        foreach ($reception->products as $product) {
            $productModel = $product->product;
            $article = $productModel ? $productModel->article : null;
            
            $rows[] = [
                $serie, // cabSerie - usando RE + año para recepciones de materia prima
                $reception->id ?: '-', // id
                $reception->date ? date('d/m/Y', strtotime($reception->date)) : '-',
                $supplier && $supplier->facil_com_code ? $supplier->facil_com_code : '-',
                $supplier && $reception->date ? $supplier->name . " - " . date('d/m/Y', strtotime($reception->date)) .' - ' . $reception->id  : '-',
                $productModel && $productModel->facil_com_code ? $productModel->facil_com_code : '-',
                $article ? $article->name : '-',
                $product->net_weight ?: '-',
                $product->price ?: '-',
            ];
        }

        // Caso especial PULPO FRESCO LONJA (si existe)
        if ($reception->declared_total_amount > 0 && $reception->declared_total_net_weight > 0) {
            $rows[] = [
                $serie, // cabSerie
                $reception->id ?: '-', // id
                $reception->date ? date('d/m/Y', strtotime($reception->date)) : '-',
                $supplier && $supplier->facil_com_code ? $supplier->facil_com_code : '-',
                $supplier && $reception->date ? $supplier->name . " - " . date('d/m/Y', strtotime($reception->date)) .' - ' . $reception->id  : '-',
                '9', // Código especial para PULPO FRESCO LONJA
                'Pulpo Fresco Compensado',
                $reception->declared_total_net_weight ? $reception->declared_total_net_weight * -1 : '-',
                $reception->declared_total_amount && $reception->declared_total_net_weight ? $reception->declared_total_amount / $reception->declared_total_net_weight : '-',
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'ALBARANESCOMPRA';
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

        // Formato de números para columnas de peso y precio (H e I)
        $sheet->getStyle('H:I')->getNumberFormat()->setFormatCode('#,##0.00');

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
