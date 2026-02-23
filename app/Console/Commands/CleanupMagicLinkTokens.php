<?php

namespace App\Console\Commands;

use App\Models\MagicLinkToken;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupMagicLinkTokens extends Command
{
    protected $signature = 'auth:cleanup-magic-tokens
                            {--tenant= : Subdomain of a single tenant to clean (optional, otherwise all active tenants)}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Elimina tokens de magic link y OTP expirados (y opcionalmente los ya usados antiguos) en las BBDD de los tenants.';

    public function handle(): int
    {
        $tenantSubdomain = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        $tenants = $tenantSubdomain
            ? Tenant::active()->where('subdomain', $tenantSubdomain)->get()
            : Tenant::active()->get();

        if ($tenants->isEmpty()) {
            $this->warn($tenantSubdomain
                ? "No se encontró ningún tenant activo con subdominio «{$tenantSubdomain}»."
                : 'No hay tenants activos.');
            return Command::FAILURE;
        }

        $totalDeleted = 0;
        $deleteExpired = config('magic_link.cleanup.delete_expired', true);
        $usedOlderThanDays = (int) config('magic_link.cleanup.used_older_than_days', 1);
        $cutoffUsed = $usedOlderThanDays > 0 ? now('UTC')->subDays($usedOlderThanDays) : null;

        foreach ($tenants as $tenant) {
            config(['database.connections.tenant.database' => $tenant->database]);
            DB::purge('tenant');
            DB::reconnect('tenant');
            config(['database.default' => 'tenant']);
            app()->instance('currentTenant', $tenant->subdomain);

            $deleted = $this->cleanupTenant($deleteExpired, $cutoffUsed, $dryRun);
            $totalDeleted += $deleted;

            if ($deleted > 0 || $this->output->isVerbose()) {
                $this->line("  [{$tenant->subdomain}] " . ($dryRun ? "Se eliminarían {$deleted} registros." : "Eliminados {$deleted} registros."));
            }
        }

        if ($dryRun && $totalDeleted > 0) {
            $this->info("Modo dry-run: en total se eliminarían {$totalDeleted} registros. Ejecuta sin --dry-run para aplicar.");
        } elseif ($totalDeleted > 0) {
            $this->info("Limpieza completada. Total eliminados: {$totalDeleted}.");
        } else {
            $this->info('Limpieza completada. No había registros que eliminar.');
        }

        return Command::SUCCESS;
    }

    private function cleanupTenant(bool $deleteExpired, ?\DateTimeInterface $cutoffUsed, bool $dryRun): int
    {
        if (!$deleteExpired && $cutoffUsed === null) {
            return 0;
        }

        $query = MagicLinkToken::query()->where(function ($q) use ($deleteExpired, $cutoffUsed) {
            if ($deleteExpired) {
                $q->where('expires_at', '<', now('UTC'));
            }
            if ($cutoffUsed !== null) {
                $q->orWhere(function ($q2) use ($cutoffUsed) {
                    $q2->whereNotNull('used_at')->where('used_at', '<', $cutoffUsed);
                });
            }
        });

        $count = $query->count();

        if (!$dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }
}
