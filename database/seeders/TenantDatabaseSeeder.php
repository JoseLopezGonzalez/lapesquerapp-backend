<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Orden: entidades del menú (catálogos) y luego usuarios. Sin Calibers (no es entidad del menú).
        $this->call(RoleSeeder::class);
        $this->call(UsersSeeder::class);
        // Productos: Categorías, Familias, Zonas de Captura, Artes de Pesca, Especies
        $this->call(ProductCategorySeeder::class);
        $this->call(ProductFamilySeeder::class);
        $this->call(CaptureZonesSeeder::class);
        $this->call(FishingGearSeeder::class);
        $this->call(SpeciesSeeder::class);
        // Clientes / Pedidos: Países, Formas de Pago, Incoterms, Transportes, Comerciales
        $this->call(CountriesSeeder::class);
        $this->call(PaymentTermsSeeder::class);
        $this->call(IncotermsSeeder::class);
        $this->call(TransportsSeeder::class);
        $this->call(SalespeopleSeeder::class);
        $this->call(TaxSeeder::class);
        // FAO zones (producción/recepciones) y operario de tienda
        $this->call(FAOZonesSeeder::class);
        $this->call(StoreOperatorUserSeeder::class);
        // Entidades operativas (pedidos, productos, cajas, palés)
        $this->call(CustomerSeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(OrderSeeder::class);
        $this->call(OrderPlannedProductDetailSeeder::class);
        $this->call(BoxSeeder::class);
        $this->call(PalletSeeder::class);
        $this->call(OrderPalletSeeder::class);

        $companyConfig = config('company');

        $flattened = Arr::dot($companyConfig); // Convierte el array en clave.valor (OJO CON ESTO, Buscar ese company config y ponerlo en el seeder cuando se deje de usar)

        foreach ($flattened as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => "company.{$key}"],
                ['value' => $value]
            );
        }
    }
}
