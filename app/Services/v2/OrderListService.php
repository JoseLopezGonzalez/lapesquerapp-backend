<?php

namespace App\Services\v2;

use App\Models\Order;
use App\Models\Pallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrderListService
{
    /**
     * Listado de pedidos: rama active (true/false) sin paginar, o listado filtrado paginado.
     * Misma lógica que OrderController::index().
     *
     * @return Collection<int, Order>|LengthAwarePaginator
     */
    public static function list(Request $request): Collection|LengthAwarePaginator
    {
        if ($request->has('active')) {
            if ($request->active == 'true') {
                return Order::withTotals()
                    ->with(['customer', 'salesperson', 'transport', 'incoterm'])
                    ->where('status', 'pending')
                    ->orWhereDate('load_date', '>=', now())
                    ->get();
            }
            return Order::withTotals()
                ->with(['customer', 'salesperson', 'transport', 'incoterm'])
                ->where('status', 'finished')
                ->whereDate('load_date', '<', now())
                ->get();
        }

        $query = Order::withTotals()->with(['customer', 'salesperson', 'transport', 'incoterm']);

        if ($request->has('customers')) {
            $query->whereIn('customer_id', $request->customers);
        }

        if ($request->has('id')) {
            $text = $request->id;
            $query->where('id', 'like', "%{$text}%");
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        if ($request->has('buyerReference')) {
            $text = $request->buyerReference;
            $query->where('buyer_reference', 'like', "%{$text}%");
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('loadDate')) {
            $loadDate = $request->input('loadDate');
            if (isset($loadDate['start'])) {
                $startDate = date('Y-m-d 00:00:00', strtotime($loadDate['start']));
                $query->where('load_date', '>=', $startDate);
            }
            if (isset($loadDate['end'])) {
                $endDate = date('Y-m-d 23:59:59', strtotime($loadDate['end']));
                $query->where('load_date', '<=', $endDate);
            }
        }

        if ($request->has('entryDate')) {
            $entryDate = $request->input('entryDate');
            if (isset($entryDate['start'])) {
                $startDate = date('Y-m-d 00:00:00', strtotime($entryDate['start']));
                $query->where('entry_date', '>=', $startDate);
            }
            if (isset($entryDate['end'])) {
                $endDate = date('Y-m-d 23:59:59', strtotime($entryDate['end']));
                $query->where('entry_date', '<=', $endDate);
            }
        }

        if ($request->has('transports')) {
            $query->whereIn('transport_id', $request->transports);
        }

        if ($request->has('salespeople')) {
            $query->whereIn('salesperson_id', $request->salespeople);
        }

        if ($request->has('palletsState')) {
            if ($request->palletsState == 'stored') {
                $query->whereHas('pallets', function ($q) {
                    $q->where('status', Pallet::STATE_STORED);
                });
            } elseif ($request->palletsState == 'shipping') {
                $query->whereHas('pallets', function ($q) {
                    $q->where('status', Pallet::STATE_SHIPPED);
                });
            }
        }

        if ($request->has('products')) {
            $query->whereHas('pallets.palletBoxes.box', function ($q) use ($request) {
                $q->whereIn('article_id', $request->products);
            });
        }

        if ($request->has('species')) {
            $query->whereHas('pallets.palletBoxes.box.product', function ($q) use ($request) {
                $q->whereIn('species_id', $request->species);
            });
        }

        if ($request->has('incoterm')) {
            $query->where('incoterm_id', $request->incoterm);
        }

        if ($request->has('transport')) {
            $query->where('transport_id', $request->transport);
        }

        $query->orderBy('load_date', 'desc');

        $perPage = $request->input('perPage', 10);

        return $query->paginate($perPage);
    }

    /**
     * Pedidos activos para Order Manager (tarjetas: estado, id, cliente, fecha de carga).
     * Status pending o load_date >= hoy.
     *
     * @return Collection<int, Order>
     */
    public static function active(): Collection
    {
        return Order::select('id', 'status', 'load_date', 'customer_id')
            ->with(['customer' => fn ($q) => $q->select('id', 'name')])
            ->where(function ($query) {
                $query->where('status', 'pending')
                    ->orWhereDate('load_date', '>=', now());
            })
            ->orderBy('load_date', 'desc')
            ->get();
    }

    /**
     * Opciones de pedidos activos (id, name=id, load_date) para desplegables.
     *
     * @return Collection<int, object>
     */
    public static function activeOrdersOptions(): Collection
    {
        return Order::where('status', 'pending')
            ->orWhereDate('load_date', '>=', now())
            ->select('id', 'id as name', 'load_date')
            ->orderBy('load_date', 'desc')
            ->get();
    }

    /**
     * Opciones de todos los pedidos (id, name=id) para desplegables.
     *
     * @return Collection<int, object>
     */
    public static function options(): Collection
    {
        return Order::select('id', 'id as name')
            ->orderBy('id')
            ->get();
    }
}
