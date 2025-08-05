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
        $query = RawMaterialReception::query();
        $query->with('supplier', 'products.product.article');

        // Aplicar filtros coordinados con el método index v2
        $this->applyFiltersToQuery($query);

        $query->orderBy('date', 'desc');

        return $query->get();
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

    public function map($rawMaterialReception): array
    {
        $mappedProducts = [];

        foreach ($rawMaterialReception->products as $product) {
            $mappedProducts[] = [
                'id' => $this->index,
                'date' => date('d/m/Y', strtotime($rawMaterialReception->date)),
                'supplierId' => $rawMaterialReception->supplier->facil_com_code,
                'supplierName' => $rawMaterialReception->supplier->name,
                'articleId' => $product->product->facil_com_code,
                'articleName' => $product->product->article->name,
                'netWeight' => $product->net_weight,
                'price' => $product->price,
                'lot' => date('dmY', strtotime($rawMaterialReception->date)),
            ];
        }

        // Caso especial PULPO FRESCO LONJA
        if ($rawMaterialReception->declared_total_amount > 0 && $rawMaterialReception->declared_total_net_weight > 0) {
            $mappedProducts[] = [
                'id' => $this->index,
                'date' => date('d/m/Y', strtotime($rawMaterialReception->date)),
                'supplierId' => $rawMaterialReception->supplier->facil_com_code,
                'supplierName' => $rawMaterialReception->supplier->name,
                'articleId' => 100,
                'articleName' => 'PULPO FRESCO LONJA',
                'netWeight' => $rawMaterialReception->declared_total_net_weight * -1,
                'price' => $rawMaterialReception->declared_total_amount / $rawMaterialReception->declared_total_net_weight,
                'lot' => date('dmY', strtotime($rawMaterialReception->date)),
            ];
        }

        $this->index++;

        return $mappedProducts;
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