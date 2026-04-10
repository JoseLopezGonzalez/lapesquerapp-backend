<?php

namespace App\Services\v2;

use App\Models\Box;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\RouteStop;
use App\Models\Tax;
use App\Services\v2\PalletTimelineService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function normalizeDateToBusiness;

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
        $fieldOperatorId = self::resolveFieldOperatorId($user);
        self::validateRouteContext($validated, $fieldOperatorId);
        [$customerId, $salespersonId] = self::resolveCustomerContext($validated, $user, $fieldOperatorId);

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
                'customer_id' => $customerId,
                'entry_date' => normalizeDateToBusiness($validated['entryDate']),
                'load_date' => normalizeDateToBusiness($validated['loadDate']),
                'salesperson_id' => $salespersonId,
                'field_operator_id' => $fieldOperatorId,
                'created_by_user_id' => $user->id,
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
                'route_id' => $validated['routeId'] ?? null,
                'route_stop_id' => $validated['routeStopId'] ?? null,
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

            $boxesCount = count($validated['boxes']);
            $totalNetWeight = array_sum(array_map(fn ($b) => (float) ($b['netWeight'] ?? 0), $validated['boxes']));
            PalletTimelineService::record($pallet, 'pallet_created', 'Palet creado (autoventa)', [
                'boxesCount' => $boxesCount,
                'totalNetWeight' => round($totalNetWeight, 2),
                'initialState' => 'shipped',
                'storeId' => null,
                'storeName' => null,
                'orderId' => $order->id,
                'fromAutoventa' => true,
            ]);

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

    private static function resolveFieldOperatorId(User $user): int
    {
        $fieldOperatorId = $user->fieldOperator?->id;

        if (! $fieldOperatorId) {
            throw ValidationException::withMessages([
                'orderType' => ['El usuario actual no tiene una identidad operativa válida para crear autoventas.'],
            ]);
        }

        return $fieldOperatorId;
    }

    private static function resolveCustomerContext(array $validated, User $user, int $fieldOperatorId): array
    {
        if (! empty($validated['newCustomerName'])) {
            $customer = Customer::create([
                'name' => trim((string) $validated['newCustomerName']),
                'alias' => null,
                'vat_number' => null,
                'payment_term_id' => null,
                'billing_address' => null,
                'shipping_address' => null,
                'transportation_notes' => null,
                'production_notes' => null,
                'accounting_notes' => null,
                'salesperson_id' => null,
                'field_operator_id' => $fieldOperatorId,
                'operational_status' => 'alta_operativa',
                'created_by_user_id' => $user->id,
                'emails' => null,
                'contact_info' => null,
                'country_id' => null,
                'transport_id' => null,
                'a3erp_code' => null,
                'facilcom_code' => null,
            ]);
            $customer->alias = 'Cliente Nº ' . $customer->id;
            $customer->save();

            return [$customer->id, null];
        }

        $customerId = $validated['customer'] ?? null;
        if (! $customerId) {
            throw ValidationException::withMessages([
                'customer' => ['Debe indicar un cliente existente o crear uno nuevo en la autoventa.'],
            ]);
        }

        $customer = Customer::find($customerId);
        if (! $customer) {
            throw ValidationException::withMessages([
                'customer' => ['El cliente seleccionado no existe.'],
            ]);
        }

        if ($customer->field_operator_id !== $fieldOperatorId) {
            throw ValidationException::withMessages([
                'customer' => ['Solo puede crear autoventas sobre clientes operativos asignados a usted.'],
            ]);
        }

        return [$customer->id, $customer->salesperson_id];
    }

    private static function validateRouteContext(array $validated, ?int $fieldOperatorId): void
    {
        $routeId = $validated['routeId'] ?? null;
        $routeStopId = $validated['routeStopId'] ?? null;

        if (! $routeId && ! $routeStopId) {
            return;
        }

        $route = $routeId ? DeliveryRoute::find($routeId) : null;
        $routeStop = $routeStopId ? RouteStop::with('route')->find($routeStopId) : null;

        if ($routeId && ! $route) {
            throw ValidationException::withMessages([
                'routeId' => ['La ruta seleccionada no existe.'],
            ]);
        }

        if ($routeStopId && ! $routeStop) {
            throw ValidationException::withMessages([
                'routeStopId' => ['La parada seleccionada no existe.'],
            ]);
        }

        if ($route && $routeStop && $routeStop->route_id !== $route->id) {
            throw ValidationException::withMessages([
                'routeStopId' => ['La parada seleccionada no pertenece a la ruta indicada.'],
            ]);
        }

        if ($fieldOperatorId && $route && $route->field_operator_id !== $fieldOperatorId) {
            throw ValidationException::withMessages([
                'routeId' => ['La ruta seleccionada no está asignada al actor operativo actual.'],
            ]);
        }

        if ($fieldOperatorId && $routeStop && $routeStop->route?->field_operator_id !== $fieldOperatorId) {
            throw ValidationException::withMessages([
                'routeStopId' => ['La parada seleccionada no pertenece a una ruta asignada al actor operativo actual.'],
            ]);
        }
    }
}
