<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\AgendaAction;
use App\Models\Customer;
use App\Models\Offer;
use App\Models\Prospect;
use App\Models\ProspectContact;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectService
{
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = Prospect::query()
            ->with(['country', 'salesperson', 'customer', 'primaryContact', 'latestInteraction', 'offers']);

        self::scopeForUser($query, $request->user());

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('company_name', 'like', '%'.$search.'%')
                    ->orWhere('address', 'like', '%'.$search.'%')
                    ->orWhere('website', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('status')) {
            $query->whereIn('status', $request->input('status'));
        }

        if ($request->filled('origin')) {
            $query->whereIn('origin', $request->input('origin'));
        }

        if ($request->filled('countries')) {
            $query->whereIn('country_id', $request->input('countries'));
        }

        if ($request->filled('salespeople')) {
            $query->whereIn('salesperson_id', $request->input('salespeople'));
        }

        return $query
            ->orderByRaw('CASE WHEN next_action_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_action_at')
            ->orderBy('company_name')
            ->paginate(min((int) $request->input('perPage', 10), 100));
    }

    public static function store(array $validated, User $user): array
    {
        $salespersonId = self::resolveSalespersonId($validated['salespersonId'] ?? null, $user);

        $prospect = Prospect::create([
            'salesperson_id' => $salespersonId,
            'company_name' => $validated['companyName'],
            'address' => $validated['address'] ?? null,
            'website' => $validated['website'] ?? null,
            'country_id' => $validated['countryId'] ?? null,
            'species_interest' => $validated['speciesInterest'] ?? [],
            'origin' => $validated['origin'] ?? Prospect::ORIGIN_OTHER,
            'status' => $validated['status'] ?? Prospect::STATUS_NEW,
            'next_action_at' => $validated['nextActionAt'] ?? null,
            'next_action_note' => $validated['nextActionNote'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'commercial_interest_notes' => $validated['commercialInterestNotes'] ?? null,
            'lost_reason' => $validated['lostReason'] ?? null,
        ]);

        if (! empty($validated['primaryContact']['name'])) {
            self::upsertPrimaryContact($prospect, $validated['primaryContact']);
        }

        $prospect->load(['country', 'salesperson', 'customer', 'primaryContact', 'latestInteraction', 'offers']);

        return [
            'prospect' => $prospect,
            'warnings' => self::detectDuplicates($prospect, $validated['primaryContact'] ?? []),
        ];
    }

    public static function update(Prospect $prospect, array $validated, User $user): array
    {
        $prospect->update([
            'salesperson_id' => self::resolveSalespersonId($validated['salespersonId'] ?? $prospect->salesperson_id, $user),
            'company_name' => $validated['companyName'],
            'address' => array_key_exists('address', $validated) ? $validated['address'] : $prospect->address,
            'website' => array_key_exists('website', $validated) ? $validated['website'] : $prospect->website,
            'country_id' => $validated['countryId'] ?? null,
            'species_interest' => $validated['speciesInterest'] ?? [],
            'origin' => $validated['origin'] ?? Prospect::ORIGIN_OTHER,
            'status' => $validated['status'] ?? $prospect->status,
            'next_action_at' => $validated['nextActionAt'] ?? null,
            'next_action_note' => array_key_exists('nextActionNote', $validated) ? $validated['nextActionNote'] : $prospect->next_action_note,
            'notes' => $validated['notes'] ?? null,
            'commercial_interest_notes' => $validated['commercialInterestNotes'] ?? null,
            'lost_reason' => $validated['lostReason'] ?? null,
        ]);

        if (array_key_exists('primaryContact', $validated)) {
            self::upsertPrimaryContact($prospect, $validated['primaryContact'] ?? []);
        }

        $prospect->load(['country', 'salesperson', 'customer', 'primaryContact', 'latestInteraction', 'offers']);

        return [
            'prospect' => $prospect,
            'warnings' => self::detectDuplicates($prospect, $validated['primaryContact'] ?? []),
        ];
    }

    public static function delete(Prospect $prospect): void
    {
        $prospect->delete();
    }

    public static function storeContact(Prospect $prospect, array $validated): ProspectContact
    {
        return DB::transaction(function () use ($prospect, $validated) {
            if (($validated['isPrimary'] ?? false) === true) {
                $prospect->contacts()->update(['is_primary' => false]);
            }

            $contact = $prospect->contacts()->create([
                'name' => $validated['name'],
                'role' => $validated['role'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'is_primary' => $validated['isPrimary'] ?? false,
            ]);

            return $contact->fresh();
        });
    }

    public static function updateContact(Prospect $prospect, ProspectContact $contact, array $validated): ProspectContact
    {
        return DB::transaction(function () use ($prospect, $contact, $validated) {
            if (($validated['isPrimary'] ?? false) === true) {
                $prospect->contacts()->update(['is_primary' => false]);
            }

            $contact->update([
                'name' => $validated['name'],
                'role' => $validated['role'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'is_primary' => $validated['isPrimary'] ?? $contact->is_primary,
            ]);

            return $contact->fresh();
        });
    }

    public static function deleteContact(ProspectContact $contact): void
    {
        $contact->delete();
    }

    public static function scheduleAction(Prospect $prospect, string $date, ?string $note = null): Prospect
    {
        DB::transaction(function () use ($prospect, $date, $note) {
            $pending = AgendaAction::query()
                ->where('target_type', CrmAgendaService::TARGET_PROSPECT)
                ->where('target_id', $prospect->id)
                ->where('status', 'pending')
                ->first();

            $previousActionId = null;
            if ($pending) {
                $previousActionId = $pending->id;
                $pending->update(['status' => 'cancelled']);
            }

            AgendaAction::create([
                'target_type' => CrmAgendaService::TARGET_PROSPECT,
                'target_id' => (int) $prospect->id,
                'scheduled_at' => $date,
                'description' => $note,
                'status' => 'pending',
                'source_interaction_id' => null,
                'completed_interaction_id' => null,
                'previous_action_id' => $previousActionId,
            ]);
        });

        $prospect->next_action_at = $date;
        $prospect->next_action_note = $note;
        $prospect->save();

        return $prospect->fresh(['country', 'salesperson', 'customer', 'primaryContact', 'latestInteraction', 'offers']);
    }

    public static function clearNextAction(Prospect $prospect): Prospect
    {
        DB::transaction(function () use ($prospect) {
            $pending = AgendaAction::query()
                ->where('target_type', CrmAgendaService::TARGET_PROSPECT)
                ->where('target_id', $prospect->id)
                ->where('status', 'pending')
                ->first();

            if ($pending) {
                $pending->update(['status' => 'cancelled']);
            }
        });

        $prospect->next_action_at = null;
        $prospect->next_action_note = null;
        $prospect->save();

        return $prospect->fresh(['country', 'salesperson', 'customer', 'primaryContact', 'latestInteraction', 'offers']);
    }

    public static function convertToCustomer(Prospect $prospect): Customer
    {
        if ($prospect->status !== Prospect::STATUS_OFFER_SENT) {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede convertir un prospecto en estado offer_sent.'],
            ]);
        }

        $primaryContact = $prospect->primaryContact()->first();
        if (! $primaryContact) {
            throw ValidationException::withMessages([
                'primaryContact' => ['Debe existir un contacto primario para convertir el prospecto.'],
            ]);
        }

        if (blank($primaryContact->phone) && blank($primaryContact->email)) {
            throw ValidationException::withMessages([
                'primaryContact' => ['El contacto primario debe tener al menos teléfono o email.'],
            ]);
        }

        return DB::transaction(function () use ($prospect, $primaryContact) {
            $acceptedOffer = $prospect->offers()
                ->where('status', Offer::STATUS_ACCEPTED)
                ->latest('accepted_at')
                ->first();

            $contactInfoParts = array_filter([
                $primaryContact->name,
                $primaryContact->role ? 'Cargo: '.$primaryContact->role : null,
                $primaryContact->phone ? 'Tel: '.$primaryContact->phone : null,
            ]);

            $address = filled($prospect->address) ? $prospect->address : null;

            $customer = Customer::create([
                'name' => $prospect->company_name,
                'country_id' => $prospect->country_id,
                'salesperson_id' => $prospect->salesperson_id,
                'billing_address' => $address,
                'shipping_address' => $address,
                'emails' => $primaryContact->email ? trim($primaryContact->email).';' : null,
                'contact_info' => implode(' | ', $contactInfoParts),
                'payment_term_id' => $acceptedOffer?->payment_term_id,
            ]);
            $customer->alias = 'Cliente Nº '.$customer->id;
            $customer->save();

            $prospect->update([
                'status' => Prospect::STATUS_CUSTOMER,
                'customer_id' => $customer->id,
            ]);

            // Transferimos la pending principal del prospecto al cliente.
            $pending = AgendaAction::query()
                ->where('target_type', CrmAgendaService::TARGET_PROSPECT)
                ->where('target_id', (int) $prospect->id)
                ->where('status', 'pending')
                ->first();

            if ($pending) {
                $customerPending = AgendaAction::query()
                    ->where('target_type', CrmAgendaService::TARGET_CUSTOMER)
                    ->where('target_id', (int) $customer->id)
                    ->where('status', 'pending')
                    ->first();

                if ($customerPending) {
                    $customerPending->update(['status' => 'cancelled']);
                }

                $pending->update([
                    'target_type' => CrmAgendaService::TARGET_CUSTOMER,
                    'target_id' => (int) $customer->id,
                ]);
            }

            $prospect->offers()
                ->update([
                    'prospect_id' => null,
                    'customer_id' => $customer->id,
                ]);

            return $customer;
        });
    }

    public static function scopeForUser(Builder $query, User $user): void
    {
        if ($user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $query->where('salesperson_id', $user->salesperson->id);
        }
    }

    private static function resolveSalespersonId(?int $salespersonId, User $user): ?int
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson?->id;
        }

        return $salespersonId;
    }

    private static function upsertPrimaryContact(Prospect $prospect, array $payload): void
    {
        if (empty($payload['name'])) {
            return;
        }

        $existingPrimary = $prospect->primaryContact()->first();
        $prospect->contacts()->update(['is_primary' => false]);

        if ($existingPrimary) {
            $existingPrimary->update([
                'name' => $payload['name'],
                'role' => $payload['role'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'email' => $payload['email'] ?? null,
                'is_primary' => true,
            ]);

            return;
        }

        $prospect->contacts()->create([
            'name' => $payload['name'],
            'role' => $payload['role'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'email' => $payload['email'] ?? null,
            'is_primary' => true,
        ]);
    }

    private static function detectDuplicates(Prospect $prospect, array $primaryContact = []): array
    {
        $warnings = [];
        $companyName = mb_strtolower(trim($prospect->company_name));

        if ($companyName !== '') {
            $existingProspects = Prospect::query()
                ->whereRaw('LOWER(company_name) = ?', [$companyName])
                ->where('id', '!=', $prospect->id)
                ->pluck('id')
                ->all();
            $existingCustomers = Customer::query()
                ->whereRaw('LOWER(name) = ?', [$companyName])
                ->pluck('id')
                ->all();

            if ($existingProspects || $existingCustomers) {
                $warnings[] = [
                    'type' => 'company_name',
                    'message' => 'Ya existe una empresa con el mismo nombre.',
                    'matches' => [
                        'prospectIds' => $existingProspects,
                        'customerIds' => $existingCustomers,
                    ],
                ];
            }
        }

        $email = trim((string) ($primaryContact['email'] ?? ''));
        if ($email !== '') {
            $existingProspectContacts = ProspectContact::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->where('prospect_id', '!=', $prospect->id)
                ->pluck('prospect_id')
                ->unique()
                ->values()
                ->all();
            $existingCustomersByEmail = Customer::query()
                ->where('emails', 'like', '%'.$email.'%')
                ->pluck('id')
                ->all();

            if ($existingProspectContacts || $existingCustomersByEmail) {
                $warnings[] = [
                    'type' => 'email',
                    'message' => 'Ya existe un email similar en otros registros.',
                    'matches' => [
                        'prospectIds' => $existingProspectContacts,
                        'customerIds' => $existingCustomersByEmail,
                    ],
                ];
            }
        }

        $phone = trim((string) ($primaryContact['phone'] ?? ''));
        if ($phone !== '') {
            $existingProspectPhones = ProspectContact::query()
                ->where('phone', $phone)
                ->where('prospect_id', '!=', $prospect->id)
                ->pluck('prospect_id')
                ->unique()
                ->values()
                ->all();
            $existingCustomersByPhone = Customer::query()
                ->where('contact_info', 'like', '%'.$phone.'%')
                ->pluck('id')
                ->all();

            if ($existingProspectPhones || $existingCustomersByPhone) {
                $warnings[] = [
                    'type' => 'phone',
                    'message' => 'Ya existe un teléfono similar en otros registros.',
                    'matches' => [
                        'prospectIds' => $existingProspectPhones,
                        'customerIds' => $existingCustomersByPhone,
                    ],
                ];
            }
        }

        return $warnings;
    }
}
