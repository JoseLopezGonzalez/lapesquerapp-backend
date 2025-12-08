<?php

namespace App\Exports\v1;

use App\Models\Box;
use App\Models\Pallet;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

class BoxesExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $filters;

    public function __construct(Request $request)
    {
        $this->filters = $request;  // Almacenar la solicitud completa puede no ser lo ideal. Es mejor pasar solo los filtros necesarios.
    }

    public function query()
    {
        $query = Box::query();

        if ($this->filters->has('id')) {
            $id = $this->filters->input('id');
            $query->whereHas('palletBox', function ($subQuery) use ($id) {
                $subQuery->where('pallet_id', 'like', "%{$id}%");
            });
        }

        if ($this->filters->has('state')) {
            if ($this->filters->input('state') == 'stored') {
                $query->whereHas('palletBox.pallet', function ($subQuery) {
                    $subQuery->where('status', Pallet::STATE_STORED);
                });
            } else if ($this->filters->input('state') == 'shipped') {
                $query->whereHas('palletBox.pallet', function ($subQuery) {
                    $subQuery->where('status', Pallet::STATE_SHIPPED);
                });
            }
        }

        if ($this->filters->has('position')) {
            if ($this->filters->input('position') == 'located') {
                $query->whereHas('palletBox.pallet', function ($subQuery) {
                    $subQuery->whereHas('storedPallet', function ($subSubQuery) {
                        $subSubQuery->whereNotNull('position');
                    });
                });
            } else if ($this->filters->input('position') == 'unlocated') {
                $query->whereHas('palletBox.pallet', function ($subQuery) {
                    $subQuery->whereHas('storedPallet', function ($subSubQuery) {
                        $subSubQuery->whereNull('position');
                    });
                });
            }
        }

        if ($this->filters->has('dates')) {
            $dates = $this->filters->input('dates');
            if (isset($dates['start'])) {
                $startDate = $dates['start'];
                $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                $query->where('created_at', '>=', $startDate);
            }
            if (isset($dates['end'])) {
                $endDate = $dates['end'];
                $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
                $query->where('created_at', '<=', $endDate);
            }
        }

        if ($this->filters->has('notes')) {
            $notes = $this->filters->input('notes');
            $query->whereHas('palletBox.pallet', function ($subQuery) use ($notes) {
                $subQuery->where('observations', 'like', "%{$notes}%");
            });
        }

        if ($this->filters->has('lots')) {
            $lots = $this->filters->input('lots');
            $query->whereIn('lot', $lots);
        }

        if ($this->filters->has('products')) {
            $articles = $this->filters->input('products');
            $query->whereIn('article_id', $articles);
        }

        return $query;
    }
    
    public function headings(): array
    {
        return [
            'ID', 
            'Articulo', 
            'Lote', 
            'Peso Neto', 
            'Peso Bruto', 
            'Fecha de lectura'
            // Agrega aquí los encabezados que desees
        ];
    }

    public function map($box): array
    {
        return [
            $box->id,
            $box->article->article->name,  // Suponiendo que Box tiene una relación con Article y que article tiene un atributo 'name'
            $box->lot,
            $box->net_weight,
            $box->gross_weight,
            $box->created_at,
            // Agrega aquí los atributos que desees exportar
        ];
    }
}
