<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\CaptureZone;
use App\Models\Customer;
use App\Models\FishingGear;
use App\Models\Incident;
use App\Models\Incoterm;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFamily;
use App\Models\Species;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Prepara un tenant + usuario admin mínimos para que Scribe pueda ejecutar ResponseCalls
 * autenticadas (config/scribe.php usa X-Tenant: demo-tenant + Bearer SCRIBE_AUTH_TOKEN).
 * Uso: SOLO en CI o en local contra una base de datos desechable — nunca en producción
 * (idempotente vía firstOrCreate, pero crea usuarios/tokens reales).
 *
 * php artisan contract:seed-fixture
 * export SCRIBE_AUTH_TOKEN=$(php artisan contract:seed-fixture --print-token-only)
 */
class SeedContractFixtureTenant extends Command
{
    protected $signature = 'contract:seed-fixture {--print-token-only : Solo imprime el token, sin logs adicionales (para capturarlo en variables de entorno)}';

    protected $description = 'Crea el tenant demo-tenant + un usuario admin para generar el contrato OpenAPI con ResponseCalls autenticadas';

    public const SUBDOMAIN = 'demo-tenant';

    public function handle(): int
    {
        $printOnly = (bool) $this->option('print-token-only');
        $database = config('database.connections.'.config('database.default').'.database');

        $tenant = Tenant::firstOrCreate(
            ['subdomain' => self::SUBDOMAIN],
            ['name' => 'Demo Tenant (contrato OpenAPI)', 'database' => $database, 'status' => 'active']
        );

        if ($tenant->status !== 'active') {
            $tenant->update(['status' => 'active']);
        }

        config(['database.connections.tenant.database' => $database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        Artisan::call('migrate', [
            '--path' => 'database/migrations/companies',
            '--database' => 'tenant',
            '--force' => true,
        ]);

        $user = User::firstOrCreate(
            ['email' => 'contract-fixture@pesquerapp.test'],
            ['name' => 'Contract Fixture Admin', 'role' => Role::Administrador->value, 'active' => true]
        );

        $this->seedSampleData();

        $token = $user->createToken('contract-fixture')->plainTextToken;

        if ($printOnly) {
            $this->line($token);

            return self::SUCCESS;
        }

        $this->info('Tenant fixture listo: '.self::SUBDOMAIN);
        $this->info('Usuario: '.$user->email);
        $this->line('Token (usar como SCRIBE_AUTH_TOKEN): '.$token);

        return self::SUCCESS;
    }

    /**
     * Datos mínimos para que ResponseCalls no reciba colecciones vacías: sin al menos un
     * registro real, Scribe no puede inferir las propiedades de los items de una colección
     * (una `data: []` no tiene forma). Idempotente por diseño de los factories (createMany
     * solo si la tabla está vacía) — no debe ejecutarse contra una BD con datos reales.
     */
    private function seedSampleData(): void
    {
        if (Order::on('tenant')->exists()) {
            return;
        }

        // Cada catálogo se siembra de forma independiente: si uno falla (p. ej. colisión de
        // un factory con un pool pequeño de valores fijos), el resto sigue adelante. Lo que
        // más importa para la riqueza del contrato es llegar a Customer/Order/Incident.
        foreach ([
            fn () => Species::factory()->count(1)->create(),
            fn () => Incoterm::factory()->count(1)->create(),
            fn () => CaptureZone::factory()->count(1)->create(),
            fn () => FishingGear::factory()->count(1)->create(),
            fn () => Tax::factory()->count(1)->create(),
            fn () => ProductCategory::factory()->count(1)->create(),
            fn () => ProductFamily::factory()->count(1)->create(),
            fn () => Store::factory()->count(1)->create(),
            fn () => Supplier::factory()->count(1)->create(),
            fn () => Product::factory()->count(2)->create(),
        ] as $seedStep) {
            try {
                $seedStep();
            } catch (\Throwable $e) {
                $this->warn('Fallo al sembrar un catálogo de ejemplo: '.$e->getMessage());
            }
        }

        try {
            $customers = Customer::factory()->count(2)->create();

            $pending = Order::factory()->pending()->create(['customer_id' => $customers->first()->id]);
            Order::factory()->finished()->create(['customer_id' => $customers->last()->id]);

            Incident::factory()->create(['order_id' => $pending->id]);
        } catch (\Throwable $e) {
            $this->warn('No se pudieron sembrar clientes/pedidos de ejemplo ('.$e->getMessage().').');
        }
    }
}
