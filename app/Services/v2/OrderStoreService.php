<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function normalizeDateToBusiness;

class OrderStoreService
{
    /**
     * Crea un pedido y sus líneas planificadas. Transacción única.
     * Si orderType es 'autoventa', delega en AutoventaStoreService.
     *
     * @param array<string, mixed> $validated Datos validados (StoreOrderRequest)
     * @param User|null $user Usuario actual (para forzar salesperson_id si es comercial)
     * @return Order Pedido creado con relaciones cargadas para OrderDetailsResource
     * @throws \Exception
     */
    public static function store(array $validated, ?User $user = null): Order
    {
        $user = $user ?? auth()->user();

        if (($validated['orderType'] ?? null) === Order::ORDER_TYPE_AUTOVENTA) {
            return AutoventaStoreService::store($validated, $user);
        }

        self::validateRouteContext($validated['routeId'] ?? null, $validated['routeStopId'] ?? null);

        $salespersonId = $validated['salesperson'] ?? null;
        if ($user && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $salespersonId = $user->salesperson->id;
        }

        $formattedEmails = self::formatEmails(
            $validated['emails'] ?? [],
            $validated['ccEmails'] ?? []
        );

        DB::beginTransaction();

        try {
            $order = Order::create([
                'customer_id' => $validated['customer'],
                'entry_date' => normalizeDateToBusiness($validated['entryDate']),
                'load_date' => normalizeDateToBusiness($validated['loadDate']),
                'salesperson_id' => $salespersonId,
                'field_operator_id' => $validated['fieldOperator'] ?? null,
                'created_by_user_id' => $user?->id,
                'payment_term_id' => $validated['payment'] ?? null,
                'incoterm_id' => $validated['incoterm'] ?? null,
                'route_id' => $validated['routeId'] ?? null,
                'route_stop_id' => $validated['routeStopId'] ?? null,
                'buyer_reference' => $validated['buyerReference'] ?? null,
                'transport_id' => $validated['transport'] ?? null,
                'truck_plate' => $validated['truckPlate'] ?? null,
                'trailer_plate' => $validated['trailerPlate'] ?? null,
                'temperature' => $validated['temperature'] ?? null,
                'billing_address' => $validated['billingAddress'] ?? null,
                'shipping_address' => $validated['shippingAddress'] ?? null,
                'transportation_notes' => $validated['transportationNotes'] ?? null,
                'production_notes' => $validated['productionNotes'] ?? null,
                'accounting_notes' => $validated['accountingNotes'] ?? null,
                'emails' => $formattedEmails ?? '',
                'status' => 'pending',
                'order_type' => $validated['orderType'] ?? Order::ORDER_TYPE_STANDARD,
            ]);

            if (!empty($validated['plannedProducts'])) {
                foreach ($validated['plannedProducts'] as $line) {
                    OrderPlannedProductDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $line['product'],
                        'tax_id' => $line['tax'],
                        'quantity' => $line['quantity'],
                        'boxes' => $line['boxes'],
                        'unit_price' => $line['unitPrice'],
                    ]);
                }
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

    private static function formatEmails(array $emails, array $ccEmails): ?string
    {
        $all = [];
        foreach ($emails as $email) {
            $all[] = trim($email);
        }
        foreach ($ccEmails as $email) {
            $all[] = 'CC:' . trim($email);
        }
        return count($all) > 0 ? implode(";\n", $all) . ';' : null;
    }

    private static function validateRouteContext(?int $routeId, ?int $routeStopId): void
    {
        if (! $routeId || ! $routeStopId) {
            return;
        }

        $route = DeliveryRoute::find($routeId);
        $routeStop = RouteStop::find($routeStopId);

        if ($route && $routeStop && $routeStop->route_id !== $route->id) {
            throw ValidationException::withMessages([
                'routeStopId' => ['La parada seleccionada no pertenece a la ruta indicada.'],
            ]);
        }
    }
}
