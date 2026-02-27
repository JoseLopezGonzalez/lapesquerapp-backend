<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Rellena el timeline de 1–2 palets con un evento de cada tipo (15 tipos).
 * Solo se ejecuta en APP_ENV=local (modo desarrollador) para pruebas y frontend.
 */
class PalletTimelineSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command->info('PalletTimelineSeeder: Solo se ejecuta en entorno local. Omitiendo.');

            return;
        }

        $pallets = Pallet::orderBy('id')->take(2)->get();
        if ($pallets->isEmpty()) {
            $this->command->warn('PalletTimelineSeeder: No hay palets. Ejecuta antes PalletSeeder.');

            return;
        }

        $user = User::first();
        $store = Store::first();
        $order = Order::first();
        $product = Product::first();

        $userId = $user?->id;
        $userName = $user?->name ?? 'Usuario desarrollo';
        $storeId = $store?->id ?? 1;
        $storeName = $store?->name ?? 'Almacén desarrollo';
        $orderId = $order?->id ?? 1;
        $orderRef = $order && $order->reference ? $order->reference : '#' . $orderId;
        $productId = $product?->id ?? 1;
        $productName = $product?->name ?? 'Producto demo';

        foreach ($pallets as $pallet) {
            $base = Carbon::now()->subDays(14);
            $timeline = [];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(0)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'pallet_created',
                'action' => 'Palet creado',
                'details' => [
                    'boxesCount' => 3,
                    'totalNetWeight' => 14.5,
                    'initialState' => 'registered',
                    'storeId' => null,
                    'storeName' => null,
                    'orderId' => null,
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(1)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'pallet_created_from_reception',
                'action' => 'Palet creado desde recepción',
                'details' => [
                    'receptionId' => 1,
                    'boxesCount' => 5,
                    'totalNetWeight' => 22.3,
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(2)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'pallet_updated',
                'action' => 'Palet actualizado',
                'details' => [
                    'observations' => ['from' => null, 'to' => 'Revisar antes de enviar'],
                    'state' => [
                        'fromId' => Pallet::STATE_REGISTERED,
                        'from' => Pallet::getStateName(Pallet::STATE_REGISTERED),
                        'toId' => Pallet::STATE_STORED,
                        'to' => Pallet::getStateName(Pallet::STATE_STORED),
                    ],
                    'store' => [
                        'assigned' => [
                            'storeId' => $storeId,
                            'storeName' => $storeName,
                            'previousStoreId' => null,
                            'previousStoreName' => null,
                        ],
                    ],
                    'order' => [
                        'linked' => [
                            'orderId' => $orderId,
                            'orderReference' => $orderRef,
                        ],
                    ],
                    'boxesAdded' => [
                        [
                            'boxId' => 1,
                            'productId' => $productId,
                            'productName' => $productName,
                            'lot' => 'L-2025-01',
                            'gs1128' => null,
                            'netWeight' => 4.2,
                            'grossWeight' => 4.5,
                            'newBoxesCount' => 4,
                            'newTotalNetWeight' => 18.7,
                        ],
                    ],
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(3)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'state_changed',
                'action' => 'Estado cambiado',
                'details' => [
                    'fromId' => Pallet::STATE_REGISTERED,
                    'from' => Pallet::getStateName(Pallet::STATE_REGISTERED),
                    'toId' => Pallet::STATE_STORED,
                    'to' => Pallet::getStateName(Pallet::STATE_STORED),
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(4)->toISOString(),
                'userId' => null,
                'userName' => 'Sistema',
                'type' => 'state_changed_auto',
                'action' => 'Estado actualizado (automático)',
                'details' => [
                    'fromId' => Pallet::STATE_STORED,
                    'from' => Pallet::getStateName(Pallet::STATE_STORED),
                    'toId' => Pallet::STATE_PROCESSED,
                    'to' => Pallet::getStateName(Pallet::STATE_PROCESSED),
                    'reason' => 'all_boxes_in_production',
                    'usedBoxesCount' => 4,
                    'totalBoxesCount' => 4,
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(5)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'store_assigned',
                'action' => 'Movido a almacén',
                'details' => [
                    'storeId' => $storeId,
                    'storeName' => $storeName,
                    'previousStoreId' => null,
                    'previousStoreName' => null,
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(6)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'store_removed',
                'action' => 'Retirado del almacén',
                'details' => [
                    'previousStoreId' => $storeId,
                    'previousStoreName' => $storeName,
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(7)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'position_assigned',
                'action' => 'Posición asignada',
                'details' => [
                    'positionId' => 12,
                    'positionName' => '12',
                    'storeId' => $storeId,
                    'storeName' => $storeName,
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(8)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'position_unassigned',
                'action' => 'Posición eliminada',
                'details' => [
                    'previousPositionId' => 12,
                    'previousPositionName' => '12',
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(9)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'order_linked',
                'action' => 'Vinculado a pedido',
                'details' => [
                    'orderId' => $orderId,
                    'orderReference' => $orderRef,
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(10)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'order_unlinked',
                'action' => 'Desvinculado de pedido',
                'details' => [
                    'orderId' => $orderId,
                    'orderReference' => $orderRef,
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(11)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'box_added',
                'action' => 'Caja añadida',
                'details' => [
                    'boxId' => 1,
                    'productId' => $productId,
                    'productName' => $productName,
                    'lot' => 'L-2025-01',
                    'gs1128' => null,
                    'netWeight' => 4.2,
                    'grossWeight' => 4.5,
                    'newBoxesCount' => 4,
                    'newTotalNetWeight' => 16.8,
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(12)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'box_removed',
                'action' => 'Caja eliminada',
                'details' => [
                    'boxId' => 1,
                    'productId' => $productId,
                    'productName' => $productName,
                    'lot' => 'L-2025-01',
                    'gs1128' => null,
                    'netWeight' => 4.2,
                    'grossWeight' => 4.5,
                    'newBoxesCount' => 3,
                    'newTotalNetWeight' => 12.6,
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(13)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'box_updated',
                'action' => 'Caja modificada',
                'details' => [
                    'boxId' => 2,
                    'productId' => $productId,
                    'productName' => $productName,
                    'lot' => 'L-2025-02',
                    'changes' => [
                        'netWeight' => ['from' => 4.0, 'to' => 4.2],
                        'lot' => ['from' => 'L-2025-01', 'to' => 'L-2025-02'],
                    ],
                ],
            ];

            $timeline[] = [
                'timestamp' => $base->copy()->addHours(14)->toISOString(),
                'userId' => $userId,
                'userName' => $userName,
                'type' => 'observations_updated',
                'action' => 'Observaciones actualizadas',
                'details' => [
                    'from' => null,
                    'to' => 'Observaciones de desarrollo para timeline',
                ],
            ];

            $pallet->timeline = $timeline;
            $pallet->save();
        }

        $this->command->info('PalletTimelineSeeder: Timeline completo asignado a ' . $pallets->count() . ' palet(s).');
    }
}
