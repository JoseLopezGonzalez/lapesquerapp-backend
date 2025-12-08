<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\v1\AutoSaleResource;
use App\Http\Resources\v1\OrderDetailsResource;
use App\Http\Resources\v1\OrderResource;
use App\Models\Box;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\Product;
use DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;



class AutoSalesController extends Controller
{
    private function generateGs1128($gtin, $lot, $netWeight)
    {
        $gs1128_01 = '(01)' . $gtin;
        $gs1128_3100 = '(3100)' . str_pad((int) round($netWeight * 100), 6, '0', STR_PAD_LEFT);

       /*  $gs1128_3100 = '(3100)' . str_pad($netWeight * 1000, 6, '0', STR_PAD_LEFT);  */// netWeight en kg → gramos
        $gs1128_10 = '(10)' . $lot;

        return $gs1128_01 . $gs1128_3100 . $gs1128_10;
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer.id' => 'required|exists:customers,id',
            'entryDate' => 'required|date',
            'loadDate' => 'nullable|date',
            'productionNotes' => 'nullable|string',
            'accountingNotes' => 'nullable|string',
            'pallet.boxes' => 'required|array|min:1',
            'pallet.boxes.*.product.id' => 'required|exists:articles,id',
            'pallet.boxes.*.lot' => 'required|string|max:255',
            'pallet.boxes.*.netWeight' => 'required|numeric|min:0',
        ]);



        DB::beginTransaction();

        try {
            $customer = Customer::findOrFail($request->customer['id']);

            $order = Order::create([
                'customer_id' => $customer->id,
                'production_notes' => $request->productionNotes,
                'accounting_notes' => $request->accountingNotes,
                'entry_date' => $request->entryDate,
                'load_date' => $request->loadDate,
                'payment_term_id' => $customer->payment_term_id,
                'billing_address' => $customer->billing_address,
                'shipping_address' => $customer->shipping_address,
                'salesperson_id' => $customer->salesperson_id,
                'emails' => $customer->emails,
                'transport_id' => $customer->transport_id,
                'incoterm_id' => 1,
                'status' => 'pending',
                'buyer_reference' => 'Autoventa',
            ]);

            $pallet = $order->pallets()->create([
                'observations' => 'Autoventa',
                'status' => Pallet::STATE_SHIPPED,
            ]);

            foreach ($request->pallet['boxes'] as $box) {
                $article = Product::find($box['product']['id']);
                $gs1_128 = $this->generateGs1128($article->box_gtin, $box['lot'], $box['netWeight']);

                $newBox = Box::create([
                    'article_id' => $article->id,
                    'lot' => $box['lot'],
                    'gross_weight' => $box['netWeight'],
                    'net_weight' => $box['netWeight'],
                    'gs1_128' => $gs1_128,
                ]);

                $pallet->palletBoxes()->create([
                    'box_id' => $newBox->id,
                ]);
            }


            DB::commit();

            return new AutoSaleResource($order);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }








}
