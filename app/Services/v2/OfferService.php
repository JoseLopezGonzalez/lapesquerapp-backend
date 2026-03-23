<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\Offer;
use App\Models\OfferLine;
use App\Models\Order;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferService
{
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = Offer::query()
            ->with(['prospect.country', 'prospect.primaryContact', 'customer.country', 'salesperson', 'incoterm', 'paymentTerm', 'order', 'lines.product', 'lines.tax']);

        self::scopeForUser($query, $request->user());

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $nested) use ($search) {
                $nested->whereHas('prospect', fn (Builder $q) => $q->where('company_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('customer', fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($request->filled('status')) {
            $query->whereIn('status', $request->input('status'));
        }

        if ($request->filled('prospectId')) {
            $query->where('prospect_id', $request->integer('prospectId'));
        }

        if ($request->filled('customerId')) {
            $query->where('customer_id', $request->integer('customerId'));
        }

        if ($request->filled('orderId')) {
            $query->where('order_id', $request->integer('orderId'));
        }

        if ($request->filled('salespeople')) {
            $query->whereIn('salesperson_id', $request->input('salespeople'));
        }

        return $query->orderByDesc('created_at')->paginate($request->input('perPage', 10));
    }

    public static function store(array $validated, User $user): Offer
    {
        self::ensureSingleTarget($validated);
        self::ensureTargetIsAccessible($validated, $user);

        return DB::transaction(function () use ($validated, $user) {
            $offer = Offer::create([
                'prospect_id' => $validated['prospectId'] ?? null,
                'customer_id' => $validated['customerId'] ?? null,
                'salesperson_id' => self::resolveSalespersonId($validated, $user),
                'status' => Offer::STATUS_DRAFT,
                'valid_until' => $validated['validUntil'] ?? null,
                'incoterm_id' => $validated['incotermId'] ?? null,
                'payment_term_id' => $validated['paymentTermId'] ?? null,
                'currency' => $validated['currency'] ?? 'EUR',
                'notes' => $validated['notes'] ?? null,
            ]);

            self::syncLines($offer, $validated['lines']);

            return $offer->load(['prospect.country', 'prospect.primaryContact', 'customer.country', 'salesperson', 'incoterm', 'paymentTerm', 'order', 'lines.product', 'lines.tax']);
        });
    }

    public static function update(Offer $offer, array $validated, User $user): Offer
    {
        if ($offer->status !== Offer::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden editar ofertas en draft.'],
            ]);
        }

        self::ensureSingleTarget($validated);
        self::ensureTargetIsAccessible($validated, $user);

        return DB::transaction(function () use ($offer, $validated) {
            $offer->update([
                'prospect_id' => $validated['prospectId'] ?? null,
                'customer_id' => $validated['customerId'] ?? null,
                'valid_until' => $validated['validUntil'] ?? null,
                'incoterm_id' => $validated['incotermId'] ?? null,
                'payment_term_id' => $validated['paymentTermId'] ?? null,
                'currency' => $validated['currency'] ?? 'EUR',
                'notes' => $validated['notes'] ?? null,
            ]);

            self::syncLines($offer, $validated['lines']);

            return $offer->load(['prospect.country', 'prospect.primaryContact', 'customer.country', 'salesperson', 'incoterm', 'paymentTerm', 'order', 'lines.product', 'lines.tax']);
        });
    }

    public static function delete(Offer $offer): void
    {
        if ($offer->status !== Offer::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden eliminar ofertas en draft.'],
            ]);
        }

        $offer->delete();
    }

    public static function markAsSent(Offer $offer, string $channel): Offer
    {
        if ($offer->status !== Offer::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden enviar ofertas en draft.'],
            ]);
        }

        $offer->update([
            'status' => Offer::STATUS_SENT,
            'send_channel' => $channel,
            'sent_at' => now('UTC'),
        ]);

        self::syncProspectStatus($offer);

        return $offer->fresh(['prospect.country', 'prospect.primaryContact', 'customer.country', 'salesperson', 'incoterm', 'paymentTerm', 'order', 'lines.product', 'lines.tax']);
    }

    public static function accept(Offer $offer): Offer
    {
        if ($offer->status !== Offer::STATUS_SENT) {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden aceptar ofertas enviadas.'],
            ]);
        }

        $offer->update([
            'status' => Offer::STATUS_ACCEPTED,
            'accepted_at' => now('UTC'),
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        self::syncProspectStatus($offer);

        return $offer->fresh(['prospect.country', 'prospect.primaryContact', 'customer.country', 'salesperson', 'incoterm', 'paymentTerm', 'order', 'lines.product', 'lines.tax']);
    }

    public static function reject(Offer $offer, string $reason): Offer
    {
        if ($offer->status !== Offer::STATUS_SENT) {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden rechazar ofertas enviadas.'],
            ]);
        }

        $offer->update([
            'status' => Offer::STATUS_REJECTED,
            'rejected_at' => now('UTC'),
            'rejection_reason' => $reason,
        ]);

        self::syncProspectStatus($offer);

        return $offer->fresh(['prospect.country', 'prospect.primaryContact', 'customer.country', 'salesperson', 'incoterm', 'paymentTerm', 'order', 'lines.product', 'lines.tax']);
    }

    public static function expire(Offer $offer): Offer
    {
        if ($offer->status === Offer::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'status' => ['Una oferta aceptada no puede expirar.'],
            ]);
        }

        $offer->update([
            'status' => Offer::STATUS_EXPIRED,
            'accepted_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        self::syncProspectStatus($offer);

        return $offer->fresh(['prospect.country', 'prospect.primaryContact', 'customer.country', 'salesperson', 'incoterm', 'paymentTerm', 'order', 'lines.product', 'lines.tax']);
    }

    public static function createOrderFromAcceptedOffer(Offer $offer, array $validated, User $user): Order
    {
        if ($offer->status !== Offer::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden crear pedidos desde ofertas aceptadas.'],
            ]);
        }

        if ($offer->order_id !== null) {
            throw ValidationException::withMessages([
                'order' => ['La oferta ya tiene un pedido asociado.'],
            ]);
        }

        $offer->loadMissing(['customer', 'prospect.primaryContact', 'lines']);

        $customerId = $offer->customer_id;
        if (! $customerId && $offer->prospect_id) {
            $customerId = ProspectService::convertToCustomer($offer->prospect)->id;
            $offer->refresh()->loadMissing('customer');
        }

        $basePlannedProducts = $offer->lines->map(function (OfferLine $line) {
            if ($line->product_id === null || $line->tax_id === null) {
                throw ValidationException::withMessages([
                    'plannedProducts' => ['Las líneas de oferta deben tener producto y taxId para crear el pedido.'],
                ]);
            }

            if ($line->boxes === null) {
                throw ValidationException::withMessages([
                    'plannedProducts' => ['Todas las líneas de la oferta deben tener boxes antes de crear el pedido.'],
                ]);
            }

            return [
                'product' => $line->product_id,
                'quantity' => $line->quantity,
                'boxes' => $line->boxes,
                'unitPrice' => $line->unit_price,
                'tax' => $line->tax_id,
            ];
        })->values()->all();

        $extraPlannedProducts = $validated['plannedProducts'] ?? [];

        $primaryEmail = $offer->prospect?->primaryContact?->email;
        $payload = [
            'customer' => $customerId,
            'entryDate' => $validated['entryDate'],
            'loadDate' => $validated['loadDate'],
            'salesperson' => $offer->salesperson_id,
            'payment' => $offer->payment_term_id,
            'incoterm' => $offer->incoterm_id,
            'buyerReference' => $validated['buyerReference'] ?? null,
            'transport' => $validated['transport'] ?? null,
            'billingAddress' => $validated['billingAddress'] ?? $offer->customer?->billing_address,
            'shippingAddress' => $validated['shippingAddress'] ?? $offer->customer?->shipping_address,
            'transportationNotes' => $validated['transportationNotes'] ?? null,
            'productionNotes' => $validated['productionNotes'] ?? null,
            'accountingNotes' => $validated['accountingNotes'] ?? null,
            'emails' => $validated['emails'] ?? ($primaryEmail ? [$primaryEmail] : []),
            'ccEmails' => $validated['ccEmails'] ?? [],
            'plannedProducts' => array_values(array_merge($basePlannedProducts, $extraPlannedProducts)),
        ];

        $order = OrderStoreService::store($payload, $user);
        $offer->update(['order_id' => $order->id]);

        return $order->fresh([
            'customer',
            'payment_term',
            'salesperson',
            'transport',
            'incoterm',
            'plannedProductDetails.product',
            'plannedProductDetails.tax',
            'incident',
            'offer',
        ]);
    }

    public static function buildWhatsappText(Offer $offer): string
    {
        $offer->loadMissing(['lines.product', 'prospect', 'customer']);

        $targetName = $offer->prospect?->company_name ?? $offer->customer?->name ?? 'cliente';
        $lines = $offer->lines->map(function (OfferLine $line) {
            return sprintf(
                '- %s | %.3f %s x %.4f %s',
                $line->description,
                $line->quantity,
                $line->unit,
                $line->unit_price,
                $line->currency
            );
        })->implode("\n");

        return trim("Oferta para {$targetName}\n\n{$lines}\n\nValidez: ".($offer->valid_until?->format('Y-m-d') ?? 'sin definir'));
    }

    public static function scopeForUser(Builder $query, User $user): void
    {
        if ($user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $query->where('salesperson_id', $user->salesperson->id);
        }
    }

    private static function resolveSalespersonId(array $validated, User $user): ?int
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson?->id;
        }

        if (! empty($validated['prospectId'])) {
            return Prospect::findOrFail($validated['prospectId'])->salesperson_id;
        }

        if (! empty($validated['customerId'])) {
            return \App\Models\Customer::findOrFail($validated['customerId'])->salesperson_id;
        }

        return null;
    }

    private static function syncLines(Offer $offer, array $lines): void
    {
        $offer->lines()->delete();

        foreach ($lines as $line) {
            $offer->lines()->create([
                'product_id' => $line['productId'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit' => $line['unit'],
                'unit_price' => $line['unitPrice'],
                'tax_id' => $line['taxId'] ?? null,
                'boxes' => $line['boxes'] ?? null,
                'currency' => $line['currency'] ?? $offer->currency,
            ]);
        }
    }

    private static function syncProspectStatus(Offer $offer): void
    {
        $offer->loadMissing('prospect');

        if (! $offer->prospect) {
            return;
        }

        $prospect = $offer->prospect->fresh();
        if (! $prospect || $prospect->status === Prospect::STATUS_CUSTOMER) {
            return;
        }

        $hasActiveOffer = $prospect->offers()
            ->whereIn('status', [Offer::STATUS_SENT, Offer::STATUS_ACCEPTED])
            ->exists();

        if ($hasActiveOffer) {
            $prospect->update([
                'status' => Prospect::STATUS_OFFER_SENT,
                'last_offer_at' => now('UTC'),
            ]);

            return;
        }

        $hasCommercialFollowUp = $prospect->next_action_at !== null
            || $prospect->last_contact_at !== null
            || $prospect->interactions()->exists();

        $prospect->update([
            'status' => $hasCommercialFollowUp ? Prospect::STATUS_FOLLOWING : Prospect::STATUS_NEW,
        ]);
    }

    private static function ensureTargetIsAccessible(array $validated, User $user): void
    {
        if (! $user->hasRole(Role::Comercial->value) || ! $user->salesperson) {
            return;
        }

        if (! empty($validated['prospectId'])) {
            $prospect = Prospect::findOrFail($validated['prospectId']);
            if ($prospect->salesperson_id !== $user->salesperson->id) {
                throw ValidationException::withMessages([
                    'prospectId' => ['No puede crear ofertas sobre prospectos de otro comercial.'],
                ]);
            }
        }

        if (! empty($validated['customerId'])) {
            $customer = \App\Models\Customer::findOrFail($validated['customerId']);
            if ($customer->salesperson_id !== $user->salesperson->id) {
                throw ValidationException::withMessages([
                    'customerId' => ['No puede crear ofertas sobre clientes de otro comercial.'],
                ]);
            }
        }
    }

    private static function ensureSingleTarget(array $validated): void
    {
        if (empty($validated['prospectId']) === empty($validated['customerId'])) {
            throw ValidationException::withMessages([
                'target' => ['Debe indicar exactamente uno entre prospectId y customerId.'],
            ]);
        }
    }
}
