<?php

namespace App\Services\v2;

use App\Models\Pallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PalletListService
{
    /**
     * Lista palets con filtros y paginación.
     *
     * @param  Request  $request  Request con query params ya validados (IndexPalletRequest)
     * @return LengthAwarePaginator
     */
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = Pallet::query();
        $query = self::loadRelations($query);
        $filters = $request->all();
        $query = self::applyFilters($query, $filters);
        $query->orderBy('id', 'desc');
        $perPage = $request->input('perPage', 10);

        return $query->paginate($perPage);
    }

    /**
     * Carga las relaciones necesarias para el PalletResource.
     * Público para uso desde PalletController (show, store, update, etc.).
     */
    public static function loadRelations(Builder $query): Builder
    {
        return $query->with([
            'storedPallet',
            'reception',
            'boxes.box.productionInputs.productionRecord.production',
            'boxes.box.product',
        ]);
    }

    /**
     * Aplica filtros al query de palets.
     * Público para uso desde PalletController cuando se necesite filtrar fuera del index.
     */
    public static function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['filters'])) {
            $filters = $filters['filters'];
        }

        if (isset($filters['id'])) {
            $query->where('id', 'like', "%{$filters['id']}%");
        }

        if (isset($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        if (!empty($filters['state'])) {
            if ($filters['state'] === 'registered') {
                $query->where('status', Pallet::STATE_REGISTERED);
            } elseif ($filters['state'] === 'stored') {
                $query->where('status', Pallet::STATE_STORED);
            } elseif ($filters['state'] === 'shipped') {
                $query->where('status', Pallet::STATE_SHIPPED);
            } elseif ($filters['state'] === 'processed') {
                $query->where('status', Pallet::STATE_PROCESSED);
            }
        }

        if (!empty($filters['orderState'])) {
            if ($filters['orderState'] === 'pending') {
                $query->whereHas('order', fn ($q) => $q->where('status', 'pending'));
            } elseif ($filters['orderState'] === 'finished') {
                $query->whereHas('order', fn ($q) => $q->where('status', 'finished'));
            } elseif ($filters['orderState'] === 'without_order') {
                $query->whereDoesntHave('order');
            }
        }

        if (!empty($filters['position'])) {
            if ($filters['position'] === 'located') {
                $query->whereHas('storedPallet', fn ($q) => $q->whereNotNull('position'));
            } elseif ($filters['position'] === 'unlocated') {
                $query->whereHas('storedPallet', fn ($q) => $q->whereNull('position'));
            }
        }

        if (!empty($filters['dates']['start'])) {
            $query->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($filters['dates']['start'])));
        }
        if (!empty($filters['dates']['end'])) {
            $query->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime($filters['dates']['end'])));
        }

        if (!empty($filters['dateFrom'])) {
            $query->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($filters['dateFrom'])));
        }
        if (!empty($filters['dateTo'])) {
            $query->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime($filters['dateTo'])));
        }

        if (!empty($filters['notes'])) {
            $query->where('observations', 'like', "%{$filters['notes']}%");
        }

        if (!empty($filters['lots'])) {
            $query->whereHas('boxes.box', fn ($q) => $q->whereIn('lot', $filters['lots']));
        }

        if (!empty($filters['products'])) {
            $query->whereHas('boxes.box', fn ($q) => $q->whereIn('article_id', $filters['products']));
        }

        if (!empty($filters['species'])) {
            $query->whereHas('boxes.box.product', fn ($q) => $q->whereIn('species_id', $filters['species']));
        }

        if (!empty($filters['stores'])) {
            $stores = is_array($filters['stores']) ? $filters['stores'] : (array) $filters['stores'];
            $query->whereHas('storedPallet', fn ($q) => $q->whereIn('store_id', $stores));
        }

        if (!empty($filters['store']['id'])) {
            $query->whereHas('storedPallet', fn ($q) => $q->where('store_id', $filters['store']['id']));
        }

        if (!empty($filters['orders'])) {
            $query->whereHas('order', fn ($q) => $q->whereIn('order_id', $filters['orders']));
        }

        if (!empty($filters['orderIds'])) {
            $query->whereHas('order', fn ($q) => $q->whereIn('id', $filters['orderIds']));
        }

        if (!empty($filters['orderDates']['start'])) {
            $query->whereHas('order', fn ($q) => $q->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($filters['orderDates']['start']))));
        }
        if (!empty($filters['orderDates']['end'])) {
            $query->whereHas('order', fn ($q) => $q->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime($filters['orderDates']['end']))));
        }

        if (!empty($filters['buyerReference'])) {
            $query->whereHas('order', fn ($q) => $q->where('buyer_reference', 'like', '%' . $filters['buyerReference'] . '%'));
        }

        if (!empty($filters['weights']['netWeight'])) {
            if (isset($filters['weights']['netWeight']['min'])) {
                $min = $filters['weights']['netWeight']['min'];
                $query->whereHas('boxes.box', fn ($q) => $q->havingRaw('sum(net_weight) >= ?', [$min]));
            }
            if (isset($filters['weights']['netWeight']['max'])) {
                $max = $filters['weights']['netWeight']['max'];
                $query->whereHas('boxes.box', fn ($q) => $q->havingRaw('sum(net_weight) <= ?', [$max]));
            }
        }

        if (!empty($filters['weights']['grossWeight'])) {
            if (isset($filters['weights']['grossWeight']['min'])) {
                $min = $filters['weights']['grossWeight']['min'];
                $query->whereHas('boxes.box', fn ($q) => $q->havingRaw('sum(gross_weight) >= ?', [$min]));
            }
            if (isset($filters['weights']['grossWeight']['max'])) {
                $max = $filters['weights']['grossWeight']['max'];
                $query->whereHas('boxes.box', fn ($q) => $q->havingRaw('sum(gross_weight) <= ?', [$max]));
            }
        }

        if (!empty($filters['hasAvailableBoxes'])) {
            if ($filters['hasAvailableBoxes'] === true || $filters['hasAvailableBoxes'] === 'true') {
                $query->whereHas('boxes.box', function ($q) {
                    $q->whereDoesntHave('productionInputs');
                });
            }
        }

        if (!empty($filters['hasUsedBoxes'])) {
            if ($filters['hasUsedBoxes'] === true || $filters['hasUsedBoxes'] === 'true') {
                $query->whereHas('boxes.box', function ($q) {
                    $q->whereHas('productionInputs');
                });
            }
        }

        return $query;
    }
}
