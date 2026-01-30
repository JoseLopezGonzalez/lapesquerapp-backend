<?php

namespace App\Exports\v2;

use App\Models\CeboDispatch;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CeboDispatchA3erp2Export implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
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
        $query = CeboDispatch::query();

        // Extraer todos los filtros aplicables del request
        $filters = $this->filters->all();

        // Aplicar filtros si existen
        if ($filters) {
            $this->applyFiltersToQuery($query, $filters);
        }

        // IMPORTANTE: Solo exportar despachos de tipo facilcom
        $query->where('export_type', 'facilcom');

        // Ordenar por fecha descendente
        $query->orderBy('date', 'desc');

        // Aplicar límite si se especifica (útil para testing)
        if ($this->limit) {
            $query->limit($this->limit);
        }

        // NOTA: Esta exportación usa FromCollection porque map() retorna múltiples filas por registro
        // (una fila por producto en cada despacho).
        // FromQuery no soporta map() que retorna múltiples filas, por lo que usamos FromCollection.
        // Para optimizar memoria, usamos eager loading con with() y limitamos con ->limit() si es necesario.
        return $query->with([
            'supplier',
            'products.product'
        ])->get();
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

        // Nota: No aplicamos filtro por export_type aquí porque ya lo forzamos a 'facilcom' en collection()
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
            'LINUNIDADES',
            'LINPRCMONEDA',
            'LINTIPIVA',
        ];
    }

    public function map($ceboDispatch): array
    {
        // Mejorar el manejo de relaciones nulas
        $supplier = $ceboDispatch->supplier;
        
        $rows = [];

        // Procesar todos los despachos de tipo facilcom, incluso si no tienen códigos
        // Los campos faltantes se mostrarán con "-" y se resaltarán en amarillo
        // (La colección ya filtra por export_type == 'facilcom', pero mantenemos la verificación por seguridad)
        if ($ceboDispatch->export_type == 'facilcom') {
            // Obtener año de 2 dígitos basado en la fecha del despacho
            $year = $ceboDispatch->date ? date('y', strtotime($ceboDispatch->date)) : '25';
            $serie = 'C' . $year;

            foreach ($ceboDispatch->products as $product) {
                $productModel = $product->product;
                
                $rows[] = [
                    $serie, // cabSerie
                    $ceboDispatch->id ?: '-', // id
                    $ceboDispatch->date ? date('d/m/Y', strtotime($ceboDispatch->date)) : '-',
                    // Usar códigos de Facilcom, mostrar "-" si no existe
                    $supplier && $supplier->facilcom_cebo_code ? $supplier->facilcom_cebo_code : '-',
                    $supplier && $ceboDispatch->date ? $supplier->name . " - CEBO - " . date('d/m/Y', strtotime($ceboDispatch->date)) : ($supplier ? $supplier->name : '-'),
                    // Usar códigos de Facilcom, mostrar "-" si no existe
                    $productModel && $productModel->facil_com_code ? $productModel->facil_com_code : '-',
                    $productModel ? $productModel->name : '-',
                    $product->net_weight ?: '-',
                    $product->price ?: '-',
                    'EXE', // iva
                ];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'ALBARANESVENTA';
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

