<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\v2\CrmAgendaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmAgendaBackfill extends Command
{
    protected $signature = 'crm:agenda-backfill {--tenant=* : Subdominios de tenants a procesar (si se omite, se procesa todo)}';

    protected $description = 'Backfill inicial de agenda_actions desde lógica legacy (prospects.next_action_* y últimas interacciones).';

    public function handle(): int
    {
        $tenantOptions = $this->option('tenant');

        $tenantsQuery = Tenant::active();
        if (! empty($tenantOptions) && is_array($tenantOptions)) {
            $tenantsQuery->whereIn('subdomain', $tenantOptions);
        }

        $tenants = $tenantsQuery->get();

        if ($tenants->isEmpty()) {
            $this->info('No hay tenants para procesar.');
            return Command::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->info("Backfilling tenant: {$tenant->subdomain}");

            config(['database.connections.tenant.database' => $tenant->database]);
            DB::purge('tenant');
            DB::reconnect('tenant');
            config(['database.default' => 'tenant']);

            $result = CrmAgendaService::backfillFromLegacy();

            $this->info("  created={$result['created']} skipped={$result['skipped']}");
        }

        return Command::SUCCESS;
    }
}

