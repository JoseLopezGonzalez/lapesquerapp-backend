<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\Order;
use App\Models\Pallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrderExportFilterService
{
    /**
     * Aplica los filtros de pedidos (compartidos por PDF y Excel) y devuelve el query.
     */
    public function applyFilters(Request $request): Builder
    {
        $query = Order::query();

        $user = $request->user();
        if ($user && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $query->where('salesperson_id', $user->salesperson->id);
        }

        if ($request->has('active')) {
            if ($request->active === 'true') {
                $query->where(function ($q) {
                    $q->where('status', 'pending')
                        ->orWhereDate('load_date', '>=', now());
                });
            } else {
                $query->where('status', 'finished')
                    ->whereDate('load_date', '<', now());
            }
        }

        if ($request->has('customers')) {
            $customers = $this->sanitizeIntegerArray($request->customers);
            if ($customers !== []) {
                $query->whereIn('customer_id', $customers);
            }
        }

        if ($request->has('id')) {
            $query->where('id', 'like', '%' . $request->id . '%');
        }

        if ($request->has('ids')) {
            $ids = $this->sanitizeIntegerArray($request->ids);
            if ($ids !== []) {
                $query->whereIn('id', $ids);
            }
        }

        if ($request->has('buyerReference')) {
            $query->where('buyer_reference', 'like', '%' . $request->buyerReference . '%');
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('loadDate')) {
            $loadDate = $request->loadDate;
            if (isset($loadDate['start'])) {
                $query->where('load_date', '>=', date('Y-m-d 00:00:00', strtotime($loadDate['start'])));
            }
            if (isset($loadDate['end'])) {
                $query->where('load_date', '<=', date('Y-m-d 23:59:59', strtotime($loadDate['end'])));
            }
        }

        if ($request->has('entryDate')) {
            $entryDate = $request->entryDate;
            if (isset($entryDate['start'])) {
                $query->where('entry_date', '>=', date('Y-m-d 00:00:00', strtotime($entryDate['start'])));
            }
            if (isset($entryDate['end'])) {
                $query->where('entry_date', '<=', date('Y-m-d 23:59:59', strtotime($entryDate['end'])));
            }
        }

        if ($request->has('transports')) {
            $transports = $this->sanitizeIntegerArray($request->transports);
            if ($transports !== []) {
                $query->whereIn('transport_id', $transports);
            }
        }

        if ($request->has('salespeople')) {
            $salespeople = $this->sanitizeIntegerArray($request->salespeople);
            if ($salespeople !== []) {
                $query->whereIn('salesperson_id', $salespeople);
            }
        }

        if ($request->has('palletsState')) {
            if ($request->palletsState === 'stored') {
                $query->whereHas('pallets', fn ($q) => $q->where('status', Pallet::STATE_STORED));
            } elseif ($request->palletsState === 'shipping') {
                $query->whereHas('pallets', fn ($q) => $q->where('status', Pallet::STATE_SHIPPED));
            }
        }

        if ($request->has('incoterm')) {
            $query->where('incoterm_id', $request->incoterm);
        }

        if ($request->has('transport')) {
            $query->where('transport_id', $request->transport);
        }

        if ($request->has('products')) {
            $products = $this->sanitizeIntegerArray($request->products);
            if ($products !== []) {
                $query->whereHas('pallets.palletBoxes.box', fn ($q) => $q->whereIn('article_id', $products));
            }
        }

        if ($request->has('species')) {
            $species = $this->sanitizeIntegerArray($request->species);
            if ($species !== []) {
                $query->whereHas('pallets.palletBoxes.box.product', fn ($q) => $q->whereIn('species_id', $species));
            }
        }

        $query->orderBy('load_date', 'desc');

        return $query;
    }

    /**
     * Aplica filtros y devuelve la colección de pedidos.
     *
     * @return Collection<int, Order>
     */
    public function getFilteredOrders(Request $request): Collection
    {
        return $this->applyFilters($request)->get();
    }

    private function sanitizeIntegerArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', $value),
            fn ($item) => $item > 0
        ));
    }
}
