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
    protected $index;
    protected $limit;

    public function __construct(Request $request, $limit = null)
    {
        $this->filters = $request;
        $this->index = 1;
        $this->limit = $limit;
    }

    public function collection()
    {
        try {
            $query = RawMaterialReception::query();

            // Extraer todos los filtros aplicables del request (igual que BoxesReportExport)
            $filters = $this->filters->all();

            // Aplicar filtros si existen
            if ($filters) {
                $this->applyFiltersToQuery($query, $filters);
            }

            $query->orderBy('date', 'desc');

            // Aplicar límite si se especifica (útil para testing)
            if ($this->limit) {
                $query->limit($this->limit);
            }

            // Cargar relaciones de forma más eficiente
            $receptions = $query->with('supplier', 'products.product.article')->get();
            $rows = [];
        } catch (\Exception $e) {
            \Log::error('Exportación Facilcom v2: Error en collection(): ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Si hay error de conexión tenant, retornar colección vacía con mensaje
            if (strpos($e->getMessage(), 'No database selected') !== false || 
                strpos($e->getMessage(), 'Invalid catalog name') !== false ||
                strpos($e->getMessage(), 'Base table or view not found') !== false) {
                
                \Log::warning('Exportación Facilcom v2: No se pudo conectar a la base de datos tenant. Error: ' . $e->getMessage());
                
                // Retornar colección vacía para evitar errores
                return collect([]);
            } else {
                throw $e; // Re-lanzar otros errores
            }
        }

        foreach ($receptions as $reception) {
            // Verificar que el supplier existe y tiene facil_com_code
            if (!$reception->supplier || !$reception->supplier->facil_com_code) {
                continue; // Saltar recepciones sin supplier o sin código facilcom
            }

            // Agregar productos regulares
            foreach ($reception->products as $product) {
                // Verificar que el producto y su artículo existen
                if (!$product->product || !$product->product->article) {
                    continue; // Saltar productos sin artículo
                }

                $rows[] = [
                    'id' => $this->index,
                    'date' => date('d/m/Y', strtotime($reception->date)),
                    'supplierId' => $reception->supplier->facil_com_code,
                    'supplierName' => $reception->supplier->name,
                    'articleId' => $product->product->facil_com_code ?? '',
                    'articleName' => $product->product->article->name,
                    'netWeight' => $product->net_weight,
                    'price' => $product->price,
                    'lot' => date('dmY', strtotime($reception->date)),
                ];
                $this->index++;
            }

            // Caso especial PULPO FRESCO LONJA
            if ($reception->declared_total_amount > 0 && $reception->declared_total_net_weight > 0) {
                $rows[] = [
                    'id' => $this->index,
                    'date' => date('d/m/Y', strtotime($reception->date)),
                    'supplierId' => $reception->supplier->facil_com_code,
                    'supplierName' => $reception->supplier->name,
                    'articleId' => 100,
                    'articleName' => 'PULPO FRESCO LONJA',
                    'netWeight' => $reception->declared_total_net_weight * -1,
                    'price' => $reception->declared_total_amount / $reception->declared_total_net_weight,
                    'lot' => date('dmY', strtotime($reception->date)),
                ];
                $this->index++;
            }
        }

        return collect($rows);
    }

    private function applyFiltersToQuery($query, $filters)
    {
        // Para aceptar filtros anidados (igual que BoxesReportExport)
        if (isset($filters['filters'])) {
            $filters = $filters['filters'];
        }

        if (isset($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        if (isset($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        if (isset($filters['suppliers'])) {
            $query->whereIn('supplier_id', $filters['suppliers']);
        }

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

        if (isset($filters['species'])) {
            $query->whereHas('products.product', function ($query) use ($filters) {
                $query->whereIn('species_id', $filters['species']);
            });
        }

        if (isset($filters['products'])) {
            $query->whereHas('products.product', function ($query) use ($filters) {
                $query->whereIn('id', $filters['products']);
            });
        }

        if (isset($filters['notes'])) {
            $query->where('notes', 'like', '%' . $filters['notes'] . '%');
        }
    }

    public function map($row): array
    {
        try {
            return [
                $row['id'] ?? '-',
                $row['date'] ?? '-',
                $row['supplierId'] ?? '-',
                $row['supplierName'] ?? '-',
                $row['articleId'] ?? '-',
                $row['articleName'] ?? '-',
                $row['netWeight'] ?? 0,
                $row['price'] ?? 0,
                $row['lot'] ?? '-',
            ];
        } catch (\Exception $e) {
            \Log::error('Exportación Facilcom v2: Error en map(): ' . $e->getMessage(), [
                'row' => $row,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
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

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para los headers
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            // Estilo para todas las celdas
            'A:I' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];
    }
} 