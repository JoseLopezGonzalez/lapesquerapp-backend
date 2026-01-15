<?php

namespace App\Exports\v1;

use App\Models\CeboDispatch;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;

class CeboDispatchA3erpExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    use Exportable;

    protected $filters;

    public function __construct(Request $request)
    {
        $this->filters = $request;
    }

    public function query()
    {
        $query = CeboDispatch::query();
        $query->with('supplier', 'products.product');

        if ($this->filters->has('id')) {
            $query->where('id', $this->filters->id);
        }

        if ($this->filters->has('suppliers')) {
            $query->whereIn('supplier_id', $this->filters->input('suppliers'));
        }

        if ($this->filters->has('dates')) {
            $dates = $this->filters->input('dates');
            $query->whereBetween('date', [$dates['start'], $dates['end']]);
        }

        if ($this->filters->has('species')) {
            $species = $this->filters->input('species');
            $query->whereHas('products.product', function ($query) use ($species) {
                $query->whereIn('species_id', $species);
            });
        }

        if ($this->filters->has('products')) {
            $products = $this->filters->input('products');
            $query->whereHas('products.product', function ($query) use ($products) {
                $query->whereIn('id', $products);
            });
        }

        if ($this->filters->has('notes')) {
            $query->where('notes', 'like', '%' . $this->filters->input('notes') . '%');
        }

        $query->orderBy('date', 'desc');

        return $query;
    }

    public function map($ceboDispatch): array
    {
        $mappedProducts = [];

        if ($ceboDispatch->export_type === 'a3erp') {
            // Obtener año de 2 dígitos basado en la fecha del despacho
            $year = $ceboDispatch->date ? date('y', strtotime($ceboDispatch->date)) : '25';
            $serie = 'C' . $year;

            foreach ($ceboDispatch->products as $product) {
                $mappedProducts[] = [
                    'cabSerie' => $serie,
                    'id' => $ceboDispatch->id,
                    'date' => date('d/m/Y', strtotime($ceboDispatch->date)),
                    'supplierId' => $ceboDispatch->supplier->a3erp_cebo_code,
                    'reference' => $ceboDispatch->supplier->name . " - CEBO - " . date('d/m/Y', strtotime($ceboDispatch->date)),
                    'articleId' => $product->product->a3erp_code,
                    'articleName' => $product->product->article->name,
                    'netWeight' => $product->net_weight,
                    'price' => $product->price,
                    'iva' => 'RED10',
                ];
            }
        }

        return $mappedProducts;
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

    public function title(): string
    {
        return 'ALBARANESVENTA';
    }
}
