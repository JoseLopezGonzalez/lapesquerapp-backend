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
            ->with(['category', 'country', 'salesperson', 'customer', 'primaryContact', 'latestInteraction.salesperson', 'offers']);

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

        if ($request->filled('categories')) {
            $query->whereIn('category_id', $request->input('categories'));
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
            'category_id' => $validated['categoryId'] ?? null,
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

        $prospect->load(['category', 'country', 'salesperson', 'customer', 'primaryContact', 'latestInteraction.salesperson', 'offers']);

        return [
            'prospect' => $prospect,
            'warnings' => self::detectDuplicates($prospect, $validated['primaryContact'] ?? []),
        ];
    }

    public static function update(Prospect $prospect, array $validated, User $user): array
    {
        $prospect->update([
            'salesperson_id' => self::resolveSalespersonId($validated['salespersonId'] ?? $prospect->salesperson_id, $user),
            'category_id' => $validated['categoryId'] ?? null,
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

        $prospect->load(['category', 'country', 'salesperson', 'customer', 'primaryContact', 'latestInteraction.salesperson', 'offers']);

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

        return $prospect->fresh(['category', 'country', 'salesperson', 'customer', 'primaryContact', 'latestInteraction.salesperson', 'offers']);
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

        return $prospect->fresh(['category', 'country', 'salesperson', 'customer', 'primaryContact', 'latestInteraction.salesperson', 'offers']);
    }

    public static function convertToCustomer(Prospect $prospect, array $extraData = []): Customer
    {
        return DB::transaction(function () use ($prospect, $extraData) {
            // Bloquear el prospecto para evitar conversiones concurrentes
            $prospect = Prospect::lockForUpdate()->findOrFail($prospect->id);
            $prospect->load('contacts');

            // Validar idempotencia: ya convertido con cliente activo
            if ($prospect->status === Prospect::STATUS_CUSTOMER && $prospect->customer_id !== null) {
                $customerExists = Customer::where('id', $prospect->customer_id)->exists();
                if ($customerExists) {
                    throw ValidationException::withMessages([
                        'status' => ['Este prospecto ya ha sido convertido a cliente.'],
                    ]);
                }
            }

            // Validar contacto primario
            $primaryContact = $prospect->contacts->firstWhere('is_primary', true);
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

            // Determinar payment_term_id: payload tiene prioridad sobre oferta aceptada
            $paymentTermId = $extraData['paymentTermId'] ?? null;
            if (! $paymentTermId) {
                $acceptedOffer = $prospect->offers()
                    ->where('status', Offer::STATUS_ACCEPTED)
                    ->latest('accepted_at')
                    ->first();
                $paymentTermId = $acceptedOffer?->payment_term_id;
            }

            // Consolidar emails de todos los contactos (sin duplicados, sin CC:)
            $emails = self::buildCustomerEmails($prospect->contacts);

            // Consolidar contact_info de todos los contactos (formato multilinea acordado)
            $contactInfo = self::buildCustomerContactInfo($prospect->contacts, $primaryContact);

            // Dirección base del prospecto (se sobreescribe si el payload la trae)
            $address = filled($prospect->address) ? $prospect->address : null;

            $customer = Customer::create([
                'name' => $prospect->company_name,
                'country_id' => $prospect->country_id,
                'salesperson_id' => $prospect->salesperson_id,
                'billing_address' => $extraData['billingAddress'] ?? $address,
                'shipping_address' => $extraData['shippingAddress'] ?? $address,
                'emails' => $emails,
                'contact_info' => $contactInfo,
                'payment_term_id' => $paymentTermId,
                'vat_number' => $extraData['vatNumber'] ?? null,
                'transport_id' => $extraData['transportId'] ?? null,
                'a3erp_code' => $extraData['a3erpCode'] ?? null,
                'facilcom_code' => $extraData['facilcomCode'] ?? null,
                'transportation_notes' => $extraData['transportationNotes'] ?? null,
                'production_notes' => $extraData['productionNotes'] ?? null,
                'accounting_notes' => $extraData['accountingNotes'] ?? null,
            ]);
            $customer->alias = 'Cliente Nº '.$customer->id;
            $customer->save();

            $prospect->update([
                'status' => Prospect::STATUS_CUSTOMER,
                'customer_id' => $customer->id,
            ]);

            // Transferir la acción pending activa del prospecto al cliente
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

            // Transferir todas las ofertas del prospecto al cliente
            $prospect->offers()->update([
                'prospect_id' => null,
                'customer_id' => $customer->id,
            ]);

            return $customer;
        });
    }

    /**
     * Consolida emails de todos los contactos en formato "email1;email2;" sin CC:.
     */
    private static function buildCustomerEmails($contacts): ?string
    {
        $seen = [];
        $parts = [];

        foreach ($contacts as $contact) {
            $email = trim((string) $contact->email);
            if (blank($email)) {
                continue;
            }
            $normalized = strtolower($email);
            if (isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            $parts[] = $email.';';
        }

        return $parts ? implode('', $parts) : null;
    }

    /**
     * Construye contact_info multilinea con todos los contactos.
     * Formato por línea: {Nombre} | Cargo: {Rol} | Tel: {Telefono} | Email: {Email}
     * El contacto primario va primero; el resto ordenados por nombre.
     */
    private static function buildCustomerContactInfo($contacts, $primaryContact): ?string
    {
        $formatContact = function ($contact): string {
            $parts = array_filter([
                filled($contact->name) ? $contact->name : null,
                filled($contact->role) ? 'Cargo: '.$contact->role : null,
                filled($contact->phone) ? 'Tel: '.$contact->phone : null,
                filled($contact->email) ? 'Email: '.$contact->email : null,
            ]);

            return implode(' | ', $parts);
        };

        // Deduplicar por combinación normalizada (name, email, phone)
        $seen = [];
        $lines = [];

        $ordered = $contacts->sortBy(function ($c) use ($primaryContact) {
            // Primario primero (sort key [0, '']), resto ordenados por nombre ([1, name])
            return $c->id === $primaryContact->id
                ? [0, '']
                : [1, (string) ($c->name ?? '')];
        });

        foreach ($ordered as $contact) {
            $key = strtolower(trim((string) $contact->name).'|'.trim((string) $contact->email).'|'.trim((string) $contact->phone));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $line = $formatContact($contact);
            if (filled($line)) {
                $lines[] = $line;
            }
        }

        return $lines ? implode("\n", $lines) : null;
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
