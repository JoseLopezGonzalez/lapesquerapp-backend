<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\OrderProfitabilityProductsRequest;
use App\Http\Requests\v2\OrderProfitabilitySummaryRequest;
use App\Http\Requests\v2\OrderProfitabilityTimelineRequest;
use App\Models\Order;
use App\Services\v2\OrderProfitabilityStatsService;

class OrderProfitabilityStatsController extends Controller
{
    public function summary(OrderProfitabilitySummaryRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $v = $request->validated();

        return response()->json(
            OrderProfitabilityStatsService::getSummary(
                $v['dateFrom'],
                $v['dateTo'],
                $v['productIds'] ?? []
            )
        );
    }

    public function timeline(OrderProfitabilityTimelineRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $v = $request->validated();

        return response()->json(
            OrderProfitabilityStatsService::getTimeline(
                $v['dateFrom'],
                $v['dateTo'],
                $v['granularity'] ?? 'month',
                $v['productIds'] ?? []
            )
        );
    }

    public function byProduct(OrderProfitabilityProductsRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $v = $request->validated();

        return response()->json(
            OrderProfitabilityStatsService::getByProduct(
                $v['dateFrom'],
                $v['dateTo']
            )
        );
    }
}
