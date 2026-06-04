<?php

namespace App\Services\v2;

use App\Models\Order;
use App\Models\Pallet;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PalletExpeditionLabelService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function labelsForOrder(Order $order): Collection
    {
        $order->loadMissing([
            'customer:id,name,alias,shipping_address',
            'transport:id,name',
            'pallets:id,observations,status,order_id',
            'pallets.boxes:id,pallet_id,box_id',
            'pallets.boxes.box:id,net_weight',
        ]);

        return $order->pallets
            ->sortBy('id')
            ->values()
            ->map(fn (Pallet $pallet) => $this->labelForPallet($order, $pallet));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function labelsForPallets(EloquentCollection $pallets): Collection
    {
        $pallets->loadMissing([
            'order:id,customer_id,transport_id,shipping_address',
            'order.customer:id,name,alias,shipping_address',
            'order.transport:id,name',
            'boxes:id,pallet_id,box_id',
            'boxes.box:id,net_weight',
        ]);

        $unlinkedPalletIds = $pallets
            ->filter(fn (Pallet $pallet) => ! $pallet->order)
            ->pluck('id')
            ->values();

        if ($unlinkedPalletIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'palletIds' => 'Los palets deben estar vinculados a un pedido para generar etiquetas de expedición: '.$unlinkedPalletIds->implode(', '),
            ]);
        }

        return $pallets
            ->sortBy('id')
            ->values()
            ->map(fn (Pallet $pallet) => $this->labelForPallet($pallet->order, $pallet));
    }

    /**
     * @return array<string, mixed>
     */
    public function labelForSinglePallet(Pallet $pallet): array
    {
        return $this->labelsForPallets(new EloquentCollection([$pallet]))->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function labelForPallet(Order $order, Pallet $pallet): array
    {
        return [
            'companyName' => tenantSetting('company.name') ?: config('app.name'),
            'palletId' => $pallet->id,
            'orderId' => $order->id,
            'orderFormattedId' => $order->formattedId,
            'customerDestination' => $this->customerDestination($order),
            'transportName' => $this->shortText($order->transport?->name, 34),
            'boxesCount' => $pallet->numberOfBoxes,
            'netWeight' => round((float) $pallet->netWeight, 2),
            'qrPayload' => $this->qrPayload($order, $pallet),
            'qrUrl' => $this->qrUrl($this->qrPayload($order, $pallet)),
        ];
    }

    private function customerDestination(Order $order): string
    {
        $customerName = $order->customer?->alias ?: $order->customer?->name;
        $destinationCity = $this->extractDestinationCity($order->shipping_address ?: $order->customer?->shipping_address);
        $destination = trim(implode(' · ', array_filter([$customerName, $destinationCity])));

        return $this->shortText($destination, 42);
    }

    private function extractDestinationCity(?string $address): ?string
    {
        if (! $address) {
            return null;
        }

        $lines = collect(preg_split('/\R+/', $address) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        foreach ($lines as $line) {
            if (preg_match('/\b\d{5}\b\s+([^,(]+)/', $line, $matches)) {
                return trim($matches[1]);
            }
        }

        return $lines->count() > 1 ? $this->shortText((string) $lines->last(), 20) : null;
    }

    private function qrPayload(Order $order, Pallet $pallet): string
    {
        return "PALLET:{$pallet->id};ORDER:{$order->id}";
    }

    private function qrUrl(string $payload): string
    {
        return 'https://barcode.tec-it.com/barcode.ashx?'.http_build_query([
            'data' => $payload,
            'code' => 'QRCode',
            'eclevel' => 'M',
        ]);
    }

    private function shortText(?string $value, int $limit): string
    {
        return Str::of((string) $value)->squish()->limit($limit, '')->toString();
    }
}
