<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Prospect;
use App\Models\User;
use Carbon\Carbon;

class CrmDashboardService
{
    public static function getPendingActionsData(User $user): array
    {
        $today = Carbon::today(config('app.business_timezone', 'Europe/Madrid'));

        // Cutover: el “feed” de próximos/hechos proviene de `agenda_actions`.
        $agendaSummary = CrmAgendaService::summary($user, 50);

        $remindersToday = collect($agendaSummary['today'] ?? [])
            ->map(fn (array $item) => self::agendaActionPayload($item, $today))
            ->sortBy('label')
            ->values()
            ->all();

        $overdueActions = collect($agendaSummary['overdue'] ?? [])
            ->map(fn (array $item) => self::agendaActionPayload($item, $today))
            ->sortByDesc('daysOverdue')
            ->values()
            ->all();

        return [
            'reminders_today' => $remindersToday,
            'overdue_actions' => $overdueActions,
            'counters' => [
                'remindersToday' => count($remindersToday),
                'overdueActions' => count($overdueActions),
            ],
        ];
    }

    public static function getCustomersData(User $user): array
    {
        $today = Carbon::today(config('app.business_timezone', 'Europe/Madrid'));
        $inactiveThreshold = $today->copy()->subDays(30);

        $customerQuery = Customer::query()->with(['country']);
        if ($user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $customerQuery->where('salesperson_id', $user->salesperson->id);
        }

        $customerIds = (clone $customerQuery)->pluck('id');
        $latestOrdersByCustomer = Order::query()
            ->selectRaw('customer_id, MAX(load_date) as last_load_date')
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $inactiveCustomers = (clone $customerQuery)
            ->get()
            ->filter(function (Customer $customer) use ($latestOrdersByCustomer, $inactiveThreshold) {
                $lastOrder = $latestOrdersByCustomer->get($customer->id)?->last_load_date;
                if (! $lastOrder) {
                    return true;
                }

                return Carbon::parse($lastOrder)->lt($inactiveThreshold);
            })
            ->values()
            ->map(function (Customer $customer) use ($latestOrdersByCustomer, $today) {
                $lastOrder = $latestOrdersByCustomer->get($customer->id)?->last_load_date;

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'country' => $customer->country?->toArrayAssoc(),
                    'daysSinceLastOrder' => $lastOrder ? Carbon::parse($lastOrder)->diffInDays($today) : null,
                    'lastOrderAt' => $lastOrder,
                ];
            })
            ->all();

        return [
            'inactive_customers' => $inactiveCustomers,
            'counters' => [
                'inactiveCustomers' => count($inactiveCustomers),
            ],
        ];
    }

    public static function getProspectsData(User $user): array
    {
        $today = Carbon::today(config('app.business_timezone', 'Europe/Madrid'));
        $prospectThreshold = $today->copy()->subDays(7);

        $prospectQuery = Prospect::query()->with(['country', 'primaryContact']);
        if ($user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $prospectQuery->where('salesperson_id', $user->salesperson->id);
        }

        $prospectsWithoutActivity = (clone $prospectQuery)
            ->where(function ($query) use ($prospectThreshold) {
                $query->whereNull('last_contact_at')
                    ->orWhere('last_contact_at', '<', $prospectThreshold);
            })
            ->orderBy('last_contact_at')
            ->get()
            ->map(function (Prospect $prospect) use ($today) {
                return [
                    'id' => $prospect->id,
                    'companyName' => $prospect->company_name,
                    'country' => $prospect->country?->toArrayAssoc(),
                    'daysWithoutActivity' => $prospect->last_contact_at
                        ? Carbon::parse($prospect->last_contact_at)->diffInDays($today)
                        : null,
                    'lastContactAt' => $prospect->last_contact_at?->toISOString(),
                ];
            })
            ->all();

        return [
            'prospects_without_activity' => $prospectsWithoutActivity,
            'counters' => [
                'prospectsWithoutActivity' => count($prospectsWithoutActivity),
            ],
        ];
    }

    private static function agendaActionPayload(array $item, Carbon $today): array
    {
        $scheduledAt = $item['scheduledAt'] ?? null;
        $daysOverdue = $scheduledAt
            ? Carbon::parse($scheduledAt)->diffInDays($today)
            : 0;

        $target = $item['target'] ?? [];
        $targetType = $target['type'] ?? null;
        $targetId = isset($target['id']) ? (int) $target['id'] : null;

        return [
            // Para que el front pueda cerrar “done” ligado a agendaActionId.
            'agendaActionId' => $item['agendaActionId'] ?? null,
            'type' => $targetType,
            'id' => $item['agendaActionId'] ?? null,
            'label' => $item['label'] ?? 'Acción pendiente',
            'nextActionAt' => $scheduledAt,
            'nextActionNote' => $item['description'] ?? null,
            'daysOverdue' => $daysOverdue,
            'prospectId' => $targetType === CrmAgendaService::TARGET_PROSPECT ? $targetId : null,
            'customerId' => $targetType === CrmAgendaService::TARGET_CUSTOMER ? $targetId : null,
        ];
    }
}
