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

    public function __construct(Request $request)
    {
        $this->filters = $request;
        $this->index = 1;
    }

    public function collection()
    {
        try {
            \Log::info('Exportación Facilcom v2: Iniciando collection()');
            
            $query = RawMaterialReception::query();
            \Log::info('Exportación Facilcom v2: Query creado');
            
            $query->with('supplier', 'products.product.article');
            \Log::info('Exportación Facilcom v2: Relaciones cargadas');

            // Aplicar filtros coordinados con el método index v2
            $this->applyFiltersToQuery($query);
            \Log::info('Exportación Facilcom v2: Filtros aplicados');

            $query->orderBy('date', 'desc');
            \Log::info('Exportación Facilcom v2: Orden aplicado');

            $receptions = $query->get();
            \Log::info('Exportación Facilcom v2: Recepciones obtenidas: ' . $receptions->count());
            
            $rows = [];
            \Log::info('Exportación Facilcom v2: Iniciando procesamiento de recepciones');
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
            \Log::info('Exportación Facilcom v2: Procesando recepción ID: ' . $reception->id);
            
            // Verificar que el supplier existe y tiene facil_com_code
            if (!$reception->supplier || !$reception->supplier->facil_com_code) {
                \Log::warning('Exportación Facilcom v2: Saltando recepción ' . $reception->id . ' - sin supplier o facil_com_code');
                continue; // Saltar recepciones sin supplier o sin código facilcom
            }

            \Log::info('Exportación Facilcom v2: Supplier válido para recepción ' . $reception->id . ': ' . $reception->supplier->facil_com_code);

            // Agregar productos regulares
            \Log::info('Exportación Facilcom v2: Procesando ' . $reception->products->count() . ' productos para recepción ' . $reception->id);
            
            foreach ($reception->products as $product) {
                \Log::info('Exportación Facilcom v2: Procesando producto ID: ' . $product->id);
                
                // Verificar que el producto y su artículo existen
                if (!$product->product || !$product->product->article) {
                    \Log::warning('Exportación Facilcom v2: Saltando producto ' . $product->id . ' - sin producto o artículo');
                    continue; // Saltar productos sin artículo
                }

                \Log::info('Exportación Facilcom v2: Producto válido: ' . $product->product->id . ' - Artículo: ' . $product->product->article->name);

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
                \Log::info('Exportación Facilcom v2: Producto agregado al índice: ' . $this->index);
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

        \Log::info('Exportación Facilcom v2: Procesamiento completado. Total de filas: ' . count($rows));
        return collect($rows);
    }

    private function applyFiltersToQuery($query)
    {
        if ($this->filters->has('id')) {
            $query->where('id', $this->filters->input('id'));
        }

        if ($this->filters->has('ids')) {
            $query->whereIn('id', $this->filters->input('ids'));
        }

        if ($this->filters->has('suppliers')) {
            $query->whereIn('supplier_id', $this->filters->input('suppliers'));
        }

        if ($this->filters->has('dates')) {
            $dates = $this->filters->input('dates');
            if (isset($dates['start'])) {
                $startDate = date('Y-m-d 00:00:00', strtotime($dates['start']));
                $query->where('date', '>=', $startDate);
            }
            if (isset($dates['end'])) {
                $endDate = date('Y-m-d 23:59:59', strtotime($dates['end']));
                $query->where('date', '<=', $endDate);
            }
        }

        if ($this->filters->has('species')) {
            $query->whereHas('products.product', function ($query) {
                $query->whereIn('species_id', $this->filters->input('species'));
            });
        }

        if ($this->filters->has('products')) {
            $query->whereHas('products.product', function ($query) {
                $query->whereIn('id', $this->filters->input('products'));
            });
        }

        if ($this->filters->has('notes')) {
            $query->where('notes', 'like', '%' . $this->filters->input('notes') . '%');
        }
    }

    public function map($row): array
    {
        try {
            return [
                $row['id'],
                $row['date'],
                $row['supplierId'],
                $row['supplierName'],
                $row['articleId'],
                $row['articleName'],
                $row['netWeight'],
                $row['price'],
                $row['lot'],
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