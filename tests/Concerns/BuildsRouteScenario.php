<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\FieldOperator;
use App\Models\Order;
use App\Models\Prospect;
use App\Models\RouteStop;
use App\Models\RouteTemplate;
use App\Models\RouteTemplateStop;
use App\Models\Salesperson;
use App\Models\Tenant;
use App\Models\User;

trait BuildsRouteScenario
{
    protected function createRouteScenario(string $slug): array
    {
        $database = config('database.connections.'.config('database.default').'.database') ?? env('DB_DATABASE', 'testing');

        Tenant::create([
            'name' => 'Routes Tenant',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $adminUser = User::create([
            'name' => 'Admin',
            'email' => $slug.'-admin@test.com',
            'role' => Role::Administrador->value,
            'active' => true,
        ]);
        $fieldUser = User::create([
            'name' => 'Field User',
            'email' => $slug.'-field@test.com',
            'role' => Role::RepartidorAutoventa->value,
            'active' => true,
        ]);
        $commercialUser = User::create([
            'name' => 'Commercial User',
            'email' => $slug.'-commercial@test.com',
            'role' => Role::Comercial->value,
            'active' => true,
        ]);

        $fieldOperator = FieldOperator::create([
            'name' => 'Repartidor Test',
            'emails' => $slug.'-field@test.com;',
            'user_id' => $fieldUser->id,
        ]);
        $commercialSalesperson = Salesperson::create([
            'name' => 'Commercial Salesperson',
            'emails' => 'commercial@test.com;',
            'user_id' => $commercialUser->id,
        ]);
        $otherSalesperson = Salesperson::create([
            'name' => 'Other Salesperson',
            'emails' => 'other@test.com;',
        ]);

        $customer = Customer::factory()->create([
            'name' => 'Cliente Ruta Test',
            'salesperson_id' => $commercialSalesperson->id,
            'field_operator_id' => $fieldOperator->id,
            'created_by_user_id' => $adminUser->id,
        ]);

        $prospect = Prospect::factory()->assignedTo($commercialSalesperson)->create([
            'company_name' => 'Prospect Ruta Test',
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'salesperson_id' => $commercialSalesperson->id,
            'field_operator_id' => $fieldOperator->id,
            'created_by_user_id' => $adminUser->id,
        ]);

        $template = RouteTemplate::create([
            'name' => 'Template base test',
            'salesperson_id' => $commercialSalesperson->id,
            'created_by_user_id' => $commercialUser->id,
            'is_active' => true,
        ]);

        $templateStop = RouteTemplateStop::create([
            'route_template_id' => $template->id,
            'position' => 1,
            'stop_type' => RouteStop::STOP_TYPE_SUGGESTED,
            'target_type' => 'customer',
            'customer_id' => $customer->id,
            'label' => $customer->name,
            'address' => $customer->shipping_address,
            'notes' => 'Stop base test',
        ]);

        $route = DeliveryRoute::create([
            'route_template_id' => $template->id,
            'name' => 'Ruta base test',
            'route_date' => now()->format('Y-m-d'),
            'status' => DeliveryRoute::STATUS_PLANNED,
            'salesperson_id' => $commercialSalesperson->id,
            'field_operator_id' => $fieldOperator->id,
            'created_by_user_id' => $commercialUser->id,
        ]);

        $routeStop = RouteStop::create([
            'route_id' => $route->id,
            'route_template_stop_id' => $templateStop->id,
            'position' => 1,
            'stop_type' => RouteStop::STOP_TYPE_SUGGESTED,
            'target_type' => 'customer',
            'customer_id' => $customer->id,
            'label' => $customer->name,
            'address' => $customer->shipping_address,
            'notes' => 'Stop de ruta base',
            'status' => RouteStop::STATUS_PENDING,
        ]);

        $order->update([
            'route_id' => $route->id,
            'route_stop_id' => $routeStop->id,
        ]);

        return compact(
            'slug',
            'adminUser',
            'fieldUser',
            'commercialUser',
            'fieldOperator',
            'commercialSalesperson',
            'otherSalesperson',
            'customer',
            'prospect',
            'order',
            'template',
            'route',
            'routeStop',
        );
    }
}
