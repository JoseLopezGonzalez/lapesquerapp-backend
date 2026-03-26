<?php

namespace Database\Seeders;

use App\Models\AgendaAction;
use App\Models\Box;
use App\Models\CommercialInteraction;
use App\Models\CostCatalog;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\Incident;
use App\Models\Label;
use App\Models\Offer;
use App\Models\OfferLine;
use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\Pallet;
use App\Models\Process;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionCost;
use App\Models\ProductionInput;
use App\Models\ProductionOutput;
use App\Models\ProductionRecord;
use App\Models\Prospect;
use App\Models\ProspectContact;
use App\Models\RawMaterialReception;
use App\Models\RouteStop;
use App\Models\RouteTemplate;
use App\Models\RouteTemplateStop;
use App\Models\Salesperson;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * Añade volumen adicional usando factories reutilizables.
 * No sustituye el seed base; solo lo amplía hasta umbrales útiles para dev.
 */
class TenantVolumeExpansionSeeder extends Seeder
{
    public function run(): void
    {
        $this->topUpCustomers(18);
        $this->topUpOrders(28);
        $this->topUpProspects(18);
        $this->topUpOffers(12);
        $this->topUpRoutes(6, 12);
        $this->topUpProductions(5);
        $this->topUpIncidents(8);
        $this->topUpLabels(8);
        $this->topUpOperationalDocuments(16, 14, 30);
    }

    private function topUpCustomers(int $target): void
    {
        $missing = max(0, $target - Customer::count());

        if ($missing === 0) {
            return;
        }

        Customer::factory()
            ->count($missing)
            ->create();
    }

    private function topUpOrders(int $target): void
    {
        $missing = max(0, $target - Order::count());

        if ($missing === 0) {
            return;
        }

        for ($index = 0; $index < $missing; $index++) {
            $state = match ($index % 3) {
                1 => Order::factory()->finished(),
                2 => Order::factory()->incident(),
                default => Order::factory()->pending(),
            };

            $state->withLines(fake()->numberBetween(2, 4))->create();
        }
    }

    private function topUpProspects(int $target): void
    {
        $missing = max(0, $target - Prospect::count());

        if ($missing === 0) {
            return;
        }

        $salespeople = Salesperson::query()->pluck('id')->all();

        for ($index = 0; $index < $missing; $index++) {
            $prospectFactory = match ($index % 4) {
                1 => Prospect::factory()->following(),
                2 => Prospect::factory()->offerSent(),
                3 => Prospect::factory()->discarded(),
                default => Prospect::factory()->new(),
            };

            $prospect = $prospectFactory
                ->assignedTo($salespeople !== [] ? $salespeople[array_rand($salespeople)] : Salesperson::factory())
                ->create();

            ProspectContact::factory()
                ->count(($index % 3) + 1)
                ->for($prospect)
                ->create();

            CommercialInteraction::factory()
                ->count(($index % 2) + 1)
                ->forProspect($prospect)
                ->create();

            if ($index % 2 === 0) {
                AgendaAction::factory()
                    ->pending()
                    ->forProspect($prospect)
                    ->create();
            }
        }
    }

    private function topUpOffers(int $target): void
    {
        $missing = max(0, $target - Offer::count());

        if ($missing === 0) {
            return;
        }

        $prospects = Prospect::query()->limit(10)->get();

        for ($index = 0; $index < $missing; $index++) {
            $prospect = $prospects->get($index % max(1, $prospects->count())) ?? Prospect::factory()->create();

            $offerFactory = match ($index % 4) {
                1 => Offer::factory()->sent(),
                2 => Offer::factory()->rejected(),
                3 => Offer::factory()->expired(),
                default => Offer::factory()->draft(),
            };

            $offer = $offerFactory
                ->forProspect($prospect)
                ->create();

            OfferLine::factory()
                ->count(fake()->numberBetween(1, 3))
                ->forOffer($offer)
                ->create();
        }
    }

    private function topUpRoutes(int $templateTarget, int $routeTarget): void
    {
        $missingTemplates = max(0, $templateTarget - RouteTemplate::count());

        for ($index = 0; $index < $missingTemplates; $index++) {
            $template = RouteTemplate::factory()->active()->create();

            RouteTemplateStop::factory()
                ->count(fake()->numberBetween(2, 4))
                ->state(new Sequence(
                    fn ($sequence) => match ($sequence->index % 3) {
                        1 => RouteTemplateStop::factory()->forProspect()->make()->getAttributes(),
                        2 => RouteTemplateStop::factory()->forLocation()->make()->getAttributes(),
                        default => RouteTemplateStop::factory()->make()->getAttributes(),
                    }
                ))
                ->for($template)
                ->create();
        }

        $missingRoutes = max(0, $routeTarget - DeliveryRoute::count());

        for ($index = 0; $index < $missingRoutes; $index++) {
            $routeFactory = match ($index % 4) {
                1 => DeliveryRoute::factory()->inProgress(),
                2 => DeliveryRoute::factory()->completed(),
                3 => DeliveryRoute::factory()->cancelled(),
                default => DeliveryRoute::factory()->planned(),
            };

            $route = $routeFactory->create();

            RouteStop::factory()
                ->count(fake()->numberBetween(2, 4))
                ->state(new Sequence(
                    fn ($sequence) => match ($sequence->index % 3) {
                        1 => RouteStop::factory()->forProspect()->completed()->make()->getAttributes(),
                        2 => RouteStop::factory()->forLocation()->pending()->make()->getAttributes(),
                        default => RouteStop::factory()->completed()->make()->getAttributes(),
                    }
                ))
                ->for($route, 'route')
                ->create();
        }
    }

    private function topUpProductions(int $target): void
    {
        $missing = max(0, $target - Production::count());

        if ($missing === 0) {
            return;
        }

        $processId = Process::query()->value('id');
        $productId = Product::query()->value('id');
        $costCatalogId = CostCatalog::query()->value('id');

        for ($index = 0; $index < $missing; $index++) {
            $productionFactory = match ($index % 2) {
                1 => Production::factory()->closed(),
                default => Production::factory()->opened(),
            };

            $production = $productionFactory->create();

            $rootRecord = ProductionRecord::factory()
                ->root()
                ->for($production)
                ->when($processId, fn ($factory) => $factory->state(['process_id' => $processId]))
                ->create();

            $childRecord = ProductionRecord::factory()
                ->childOf($rootRecord)
                ->completed()
                ->when($processId, fn ($factory) => $factory->state(['process_id' => $processId]))
                ->create();

            $availableBoxId = Box::query()
                ->whereDoesntHave('productionInputs')
                ->value('id');

            if ($availableBoxId) {
                ProductionInput::factory()
                    ->state([
                        'production_record_id' => $rootRecord->id,
                        'box_id' => $availableBoxId,
                    ])
                    ->create();
            }

            if ($productId) {
                $output = ProductionOutput::factory()
                    ->state([
                        'production_record_id' => $childRecord->id,
                        'product_id' => $productId,
                    ])
                    ->create();

                if ($costCatalogId) {
                    ProductionCost::factory()
                        ->forRecord($childRecord)
                        ->state([
                            'cost_catalog_id' => $costCatalogId,
                            'name' => 'Coste extra volumen',
                        ])
                        ->create();
                }
            }
        }
    }

    private function topUpIncidents(int $target): void
    {
        $missing = max(0, $target - Incident::count());

        if ($missing === 0) {
            return;
        }

        for ($index = 0; $index < $missing; $index++) {
            $factory = $index % 2 === 0
                ? Incident::factory()->open()
                : Incident::factory()->resolved();

            $factory->create();
        }
    }

    private function topUpLabels(int $target): void
    {
        $missing = max(0, $target - Label::count());

        if ($missing === 0) {
            return;
        }

        Label::factory()->count($missing)->create();
    }

    private function topUpOperationalDocuments(int $receptionsTarget, int $dispatchesTarget, int $palletTarget): void
    {
        $missingReceptions = max(0, $receptionsTarget - RawMaterialReception::count());

        if ($missingReceptions > 0) {
            RawMaterialReception::factory()->count($missingReceptions)->create();
        }

        $dispatchClass = \App\Models\CeboDispatch::class;
        $missingDispatches = max(0, $dispatchesTarget - $dispatchClass::count());

        if ($missingDispatches > 0) {
            $dispatchClass::factory()->count($missingDispatches)->create();
        }

        $missingPallets = max(0, $palletTarget - Pallet::count());

        if ($missingPallets > 0) {
            Pallet::factory()->count($missingPallets)->create();
        }
    }
}
