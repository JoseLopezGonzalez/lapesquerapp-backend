<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class FieldProductController extends Controller
{
    public function options(Request $request)
    {
        $this->authorize('viewOperationalOptions', Product::class);

        $products = Product::query()
            ->select('id', 'name', 'species_id')
            ->with(['species:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'species' => $product->species ? [
                    'id' => $product->species->id,
                    'name' => $product->species->name,
                ] : null,
            ]);

        return response()->json($products->values());
    }
}
