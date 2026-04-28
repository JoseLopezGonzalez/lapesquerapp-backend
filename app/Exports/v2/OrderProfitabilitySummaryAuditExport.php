<?php

namespace App\Exports\v2;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrderProfitabilitySummaryAuditExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private readonly array $data,
        private readonly bool $onlyMissingCosts = false
    ) {}

    public function sheets(): array
    {
        $sheets = [
            new OrderProfitabilitySummaryAuditSheet(
                'Resumen',
                [
                    'Metrica',
                    'Valor',
                ],
                $this->data['summary']
            ),
        ];

        if ($this->onlyMissingCosts) {
            $sheets[] = new OrderProfitabilitySummaryAuditSheet(
                'Cajas sin coste',
                $this->missingCostHeadings(),
                $this->data['missingCosts'],
                $this->missingCostKeys()
            );

            return $sheets;
        }

        return [
            ...$sheets,
            new OrderProfitabilitySummaryAuditSheet(
                'Detalle cajas',
                $this->detailHeadings(),
                $this->data['detail']
            ),
            new OrderProfitabilitySummaryAuditSheet(
                'Cajas sin coste',
                $this->missingCostHeadings(),
                $this->data['missingCosts'],
                $this->missingCostKeys()
            ),
            new OrderProfitabilitySummaryAuditSheet(
                'Resumen pedidos',
                $this->ordersHeadings(),
                $this->data['orders']
            ),
        ];
    }

    private function detailHeadings(): array
    {
        return [
            'Pedido ID',
            'Pedido',
            'Fecha carga',
            'Cliente ID',
            'Cliente',
            'Palet ID',
            'Caja ID',
            'Producto ID',
            'Producto',
            'Lote',
            'Kg netos',
            'Precio unitario',
            'Venta',
            'Coste/kg',
            'Coste total',
            'Margen bruto',
            'Margen %',
            'Estado coste',
            'Origen coste',
            'Disponible',
            'Incluida en resumen',
            'Motivo exclusion',
            'Coste manual/kg',
            'Coste manual total',
            'Notas',
        ];
    }

    private function missingCostHeadings(): array
    {
        return [
            'Caja ID',
            'Pedido ID',
            'Pedido',
            'Fecha carga',
            'Cliente',
            'Palet ID',
            'Producto ID',
            'Producto',
            'Lote',
            'Kg netos',
            'Precio unitario',
            'Venta',
            'Coste manual/kg',
            'Coste manual total',
            'Notas',
        ];
    }

    private function missingCostKeys(): array
    {
        return [
            'box_id',
            'order_id',
            'order_formatted_id',
            'load_date',
            'customer_name',
            'pallet_id',
            'product_id',
            'product_name',
            'lot',
            'net_weight_kg',
            'unit_price',
            'revenue',
            'manual_cost_per_kg',
            'manual_total_cost',
            'notes',
        ];
    }

    private function ordersHeadings(): array
    {
        return [
            'Pedido ID',
            'Pedido',
            'Fecha carga',
            'Cliente',
            'Cajas',
            'Cajas sin coste',
            'Kg netos',
            'Venta',
            'Coste conocido',
            'Margen bruto',
            'Margen %',
            'Tiene costes faltantes',
        ];
    }
}

class OrderProfitabilitySummaryAuditSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly array $rows,
        private readonly ?array $keys = null
    ) {}

    public function array(): array
    {
        if ($this->keys === null) {
            return array_map('array_values', $this->rows);
        }

        return array_map(function (array $row): array {
            return array_map(fn (string $key) => $row[$key] ?? null, $this->keys);
        }, $this->rows);
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet): array
    {
        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2F5597'],
            ],
        ]);
        $sheet->freezePane('A2');

        return [];
    }
}
