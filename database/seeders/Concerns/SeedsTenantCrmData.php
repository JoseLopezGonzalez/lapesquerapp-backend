<?php

namespace Database\Seeders\Concerns;

use App\Enums\Role;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Incoterm;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Salesperson;
use App\Models\Tax;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

trait SeedsTenantCrmData
{
    protected function crmPrimaryCommercialUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'crm.comercial@pesquerapp.com'],
            [
                'name' => 'CRM Comercial Principal',
                'role' => Role::Comercial->value,
                'active' => true,
            ]
        );
    }

    protected function crmSecondaryCommercialUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'crm.comercial.2@pesquerapp.com'],
            [
                'name' => 'CRM Comercial Secundario',
                'role' => Role::Comercial->value,
                'active' => true,
            ]
        );
    }

    protected function crmAdminUser(): User
    {
        return User::query()->where('role', Role::Administrador->value)->first()
            ?? User::query()->firstOrCreate(
                ['email' => 'crm.admin@pesquerapp.com'],
                [
                    'name' => 'CRM Admin Global',
                    'role' => Role::Administrador->value,
                    'active' => true,
                ]
            );
    }

    protected function crmPrimarySalesperson(): Salesperson
    {
        $user = $this->crmPrimaryCommercialUser();

        return tap(
            Salesperson::query()->updateOrCreate(
                ['name' => 'CRM Comercial Principal'],
                [
                    'emails' => 'crm.comercial@pesquerapp.com;',
                    'user_id' => $user->id,
                ]
            ),
            fn (Salesperson $salesperson) => $salesperson->update(['user_id' => $user->id])
        );
    }

    protected function crmSecondarySalesperson(): Salesperson
    {
        $user = $this->crmSecondaryCommercialUser();

        return tap(
            Salesperson::query()->updateOrCreate(
                ['name' => 'CRM Comercial Secundario'],
                [
                    'emails' => 'crm.comercial.2@pesquerapp.com;',
                    'user_id' => $user->id,
                ]
            ),
            fn (Salesperson $salesperson) => $salesperson->update(['user_id' => $user->id])
        );
    }

    protected function crmCountry(): Country
    {
        return Country::query()->firstOrCreate(['name' => 'Italia CRM']);
    }

    protected function crmPaymentTerm(): PaymentTerm
    {
        return PaymentTerm::query()->firstOrCreate(['name' => 'Pago contado CRM']);
    }

    protected function crmIncoterm(): Incoterm
    {
        return Incoterm::query()->firstOrCreate(
            ['code' => 'FOB'],
            ['description' => 'Free on Board']
        );
    }

    protected function crmTax(): Tax
    {
        return Tax::query()->firstOrCreate(
            ['name' => 'IVA CRM'],
            ['rate' => 21]
        );
    }

    protected function crmTransport(): Transport
    {
        return Transport::query()->firstOrCreate(
            ['name' => 'Transport CRM'],
            [
                'vat_number' => 'BCRM00001',
                'address' => 'Muelle Comercial 1',
                'emails' => 'transport.crm@test.com;',
            ]
        );
    }

    protected function crmProductPool(int $limit = 3): Collection
    {
        $products = Product::query()->orderBy('id')->limit($limit)->get();

        if ($products->isEmpty()) {
            $products = Product::factory()->count($limit)->create();
        }

        return $products;
    }

    protected function crmCustomerByName(string $name): ?Customer
    {
        return Customer::query()->where('name', $name)->first();
    }
}
