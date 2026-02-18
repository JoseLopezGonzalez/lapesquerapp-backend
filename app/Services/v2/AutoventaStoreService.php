<?php

namespace App\Services\v2;

use App\Models\Box;
use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutoventaStoreService
{
    /**
     * Crea una autoventa: Order (order_type=autoventa), OrderPlannedProductDetails,
     * un Pallet en estado enviado y las cajas (Box + PalletBox).
     *
     * @param array<string, mixed> $validated Datos validados (orderType=autoventa, customer, entryDate, loadDate, invoiceRequired, observations, items, boxes)
     * @param User $user Usuario comercial (salesperson_id se usa para el pedido)
     * @return Order Pedido creado con relaciones cargadas
     * @throws \Throwable
     */
    public static function store(array $validated, User $user): Order
    {
        $salespersonId = $user->salesperson?->id;
        if (! $salespersonId) {
            throw ValidationException::withMessages([
                'orderType' => ['Solo los usuarios con rol comercial pueden crear autoventas.'],
            ]);
        }

        $defaultTaxId = Tax::query()->value('id');
        if (! $defaultTaxId) {
            throw ValidationException::withMessages([
                'general' => ['No hay ningún impuesto configurado. Cree al menos uno en Catálogos para poder registrar autoventas.'],
            ]);
        }

        $accountingNotes = self::buildAccountingNotes(
            (bool) ($validated['invoiceRequired'] ?? false),
            $validated['observations'] ?? ''
        );

        DB::beginTransaction();

        try {
            $order = Order::create([
                'customer_id' => $validated['customer'],
                'entry_date' => $validated['entryDate'],
                'load_date' => $validated['loadDate'],
                'salesperson_id' => $salespersonId,
                'order_type' => Order::ORDER_TYPE_AUTOVENTA,
                'accounting_notes' => $accountingNotes,
                'status' => Order::STATUS_PENDING,
                'payment_term_id' => null,
                'incoterm_id' => null,
                'buyer_reference' => null,
                'transport_id' => null,
                'billing_address' => null,
                'shipping_address' => null,
                'transportation_notes' => null,
                'production_notes' => null,
                'emails' => null,
            ]);

            foreach ($validated['items'] as $item) {
                OrderPlannedProductDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $item['productId'],
                    'tax_id' => $item['tax'] ?? $defaultTaxId,
                    'quantity' => (float) $item['totalWeight'],
                    'boxes' => (int) $item['boxesCount'],
                    'unit_price' => (float) $item['unitPrice'],
                ]);
            }

            $pallet = new Pallet;
            $pallet->observations = null;
            $pallet->status = Pallet::STATE_SHIPPED;
            $pallet->order_id = $order->id;
            $pallet->save();

            foreach ($validated['boxes'] as $index => $boxData) {
                $netWeight = (float) $boxData['netWeight'];
                $lot = trim((string) ($boxData['lot'] ?? ''));
                if ($lot === '') {
                    $lot = 'AUTOVENTA-' . $order->id . '-' . ($index + 1);
                }
                $newBox = Box::create([
                    'article_id' => $boxData['productId'],
                    'lot' => $lot,
                    'gs1_128' => $boxData['gs1128'] ?? null,
                    'gross_weight' => $boxData['grossWeight'] ?? $netWeight,
                    'net_weight' => $netWeight,
                ]);
                PalletBox::create([
                    'pallet_id' => $pallet->id,
                    'box_id' => $newBox->id,
                ]);
            }

            DB::commit();

            $order->load([
                'pallets.boxes.box.productionInputs',
                'pallets.boxes.box.product.species.fishingGear',
            ]);

            return $order;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private static function buildAccountingNotes(bool $invoiceRequired, string $observations): string
    {
        $parts = [$invoiceRequired ? 'Con factura' : 'Sin factura'];
        if ($observations !== '') {
            $parts[] = $observations;
        }

        return implode("\n", $parts);
    }
}
