<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\JsonResponse;

class TaxController extends Controller
{
    public function options(): JsonResponse
    {
        $this->authorize('viewAny', Tax::class);

        $taxes = Tax::select('id', 'name', 'rate')
            ->orderBy('rate', 'asc')
            ->get();

        return response()->json($taxes);
    }
}
