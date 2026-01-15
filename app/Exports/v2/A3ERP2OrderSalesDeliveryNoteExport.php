<?php

namespace App\Exports\v2;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;

class A3ERP2OrderSalesDeliveryNoteExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    use Exportable;

    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function collection()
    {
        $rows = [];

        // Solo procesar si el cliente tiene código Facilcom
        if ($this->order->customer && $this->order->customer->facilcom_code) {
            // Obtener año de 2 dígitos basado en la fecha del pedido
            $year = date('y', strtotime($this->order->load_date));
            $serie = 'P' . $year;

            foreach ($this->order->productDetails as $productDetail) {
                $rows[] = [
                    'CABSERIE' => $serie,
                    'CABNUMDOC' => $this->order->id,
                    'CABFECHA' => date('d/m/Y', strtotime($this->order->load_date)),
                    // Usar código Facilcom en lugar de A3ERP
                    'CABCODCLI' => $this->order->customer->facilcom_code,
                    'CABREFERENCIA' => $this->order->id,
                    // Usar código Facilcom en lugar de A3ERP
                    'LINCODART' => $productDetail['product']['facilcomCode'] ?? '',
                    'LINDESCLIN' => $productDetail['product']['name'] ?? '',
                    'LINBULTOS' => $productDetail['boxes'],
                    'LINUNIDADES' => $productDetail['netWeight'],
                    'LINPRCMONEDA' => $productDetail['unitPrice'],
                    'LINTIPIVA' => $productDetail['tax']['name'] ?? '',
                ];
            }
        }

        return collect($rows);
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
            'LINBULTOS',
            'LINUNIDADES',
            'LINPRCMONEDA',
            'LINTIPIVA'
        ];
    }

    public function map($row): array
    {
        return [
            $row['CABSERIE'],
            $row['CABNUMDOC'],
            $row['CABFECHA'],
            $row['CABCODCLI'],
            $row['CABREFERENCIA'],
            $row['LINCODART'],
            $row['LINDESCLIN'],
            $row['LINBULTOS'],
            $row['LINUNIDADES'],
            $row['LINPRCMONEDA'],
            $row['LINTIPIVA']
        ];
    }

    /**
     * Nombre personalizado de la hoja
     */
    public function title(): string
    {
        return 'ALBARANESVENTA';
    }
}

