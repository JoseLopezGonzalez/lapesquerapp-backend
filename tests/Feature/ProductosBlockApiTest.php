<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CaptureZone;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFamily;
use App\Models\Species;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\CaptureZonesSeeder;
use Database\Seeders\FishingGearSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\ProductFamilySeeder;
use Database\Seeders\SpeciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for Productos block: products, product-categories, product-families.
 * Uses tenant + Sanctum auth.
 */
class ProductosBlockApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private ?string $token = null;

    private ?string $tenantSubdomain = null;

    private bool $catalogSeeded = false;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $this->createTenantAndUser();
    }

    private function seedCatalog(): void
    {
        if ($this->catalogSeeded) {
            return;
        }
        $this->catalogSeeded = true;
        (new FishingGearSeeder)->run();
        (new CaptureZonesSeeder)->run();
        (new SpeciesSeeder)->run();
        (new ProductCategorySeeder)->run();
        (new ProductFamilySeeder)->run();
    }

    private function createTenantAndUser(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'productos-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Productos',
            'subdomain' => $slug,
            'database' => $database,
            'active' => true,
        ]);

        $user = User::create([
            'name' => 'Test User Productos',
            'email' => $slug . '@test.com',
            'password' => bcrypt('password'),
            'role' => Role::Administrador->value,
        ]);

        $this->token = $user->createToken('test')->plainTextToken;
        $this->tenantSubdomain = $slug;
    }

    private function authHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    // ---------- ProductCategory ----------

    public function test_product_categories_list_returns_paginated(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/product-categories');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_product_categories_options_returns_active_only(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/product-categories/options');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    public function test_product_category_can_be_created(): void
    {
        $name = 'Categoría Test ' . uniqid();
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/product-categories', [
                'name' => $name,
                'description' => 'Desc test',
                'active' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'name', 'description', 'active']]);
        $response->assertJsonPath('data.name', $name);
        $this->assertDatabaseHas('product_categories', ['name' => $name], 'tenant');
    }

    public function test_product_category_can_be_shown(): void
    {
        $category = ProductCategory::create([
            'name' => 'Categoría Show ' . uniqid(),
            'description' => 'Desc',
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/product-categories/' . $category->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $category->id);
        $response->assertJsonPath('data.name', $category->name);
    }

    public function test_product_category_can_be_updated(): void
    {
        $category = ProductCategory::create([
            'name' => 'Categoría Update ' . uniqid(),
            'description' => 'Desc',
            'active' => true,
        ]);
        $newName = 'Categoría Actualizada ' . uniqid();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/product-categories/' . $category->id, [
                'name' => $newName,
                'description' => $category->description,
                'active' => $category->active,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', $newName);
        $this->assertDatabaseHas('product_categories', ['id' => $category->id, 'name' => $newName], 'tenant');
    }

    public function test_product_category_can_be_deleted_when_no_families(): void
    {
        $category = ProductCategory::create([
            'name' => 'Categoría Destroy ' . uniqid(),
            'description' => 'Desc',
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/product-categories/' . $category->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('product_categories', ['id' => $category->id], 'tenant');
    }

    public function test_product_category_destroy_returns_400_when_has_families(): void
    {
        $category = ProductCategory::create([
            'name' => 'Categoría Con Familias ' . uniqid(),
            'description' => 'Desc',
            'active' => true,
        ]);
        ProductFamily::create([
            'name' => 'Familia bajo categoría ' . uniqid(),
            'category_id' => $category->id,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/product-categories/' . $category->id);

        $response->assertStatus(400);
        $response->assertJsonFragment(['userMessage' => 'No se puede eliminar la categoría porque tiene familias asociadas']);
        $this->assertDatabaseHas('product_categories', ['id' => $category->id], 'tenant');
    }

    public function test_product_categories_destroy_multiple(): void
    {
        $cat1 = ProductCategory::create(['name' => 'Cat Multi 1 ' . uniqid(), 'active' => true]);
        $cat2 = ProductCategory::create(['name' => 'Cat Multi 2 ' . uniqid(), 'active' => true]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/product-categories', ['ids' => [$cat1->id, $cat2->id]]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'userMessage', 'deletedCount', 'errors']);
        $this->assertGreaterThanOrEqual(1, $response->json('deletedCount'));
        $this->assertDatabaseMissing('product_categories', ['id' => $cat1->id], 'tenant');
        $this->assertDatabaseMissing('product_categories', ['id' => $cat2->id], 'tenant');
    }

    // ---------- ProductFamily ----------

    public function test_product_families_list_returns_paginated(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/product-families');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_product_families_options_returns_array(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/product-families/options');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    public function test_product_family_can_be_created(): void
    {
        $category = ProductCategory::create([
            'name' => 'Cat para Familia ' . uniqid(),
            'active' => true,
        ]);
        $name = 'Familia Test ' . uniqid();
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/product-families', [
                'name' => $name,
                'description' => 'Desc familia',
                'categoryId' => $category->id,
                'active' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'name', 'categoryId', 'active']]);
        $response->assertJsonPath('data.name', $name);
        $this->assertDatabaseHas('product_families', ['name' => $name], 'tenant');
    }

    public function test_product_family_can_be_shown(): void
    {
        $category = ProductCategory::create(['name' => 'Cat Show ' . uniqid(), 'active' => true]);
        $family = ProductFamily::create([
            'name' => 'Familia Show ' . uniqid(),
            'category_id' => $category->id,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/product-families/' . $family->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $family->id);
        $response->assertJsonPath('data.name', $family->name);
    }

    public function test_product_family_can_be_updated(): void
    {
        $category = ProductCategory::create(['name' => 'Cat Upd ' . uniqid(), 'active' => true]);
        $family = ProductFamily::create([
            'name' => 'Familia Update ' . uniqid(),
            'category_id' => $category->id,
            'active' => true,
        ]);
        $newName = 'Familia Actualizada ' . uniqid();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/product-families/' . $family->id, [
                'name' => $newName,
                'description' => $family->description,
                'categoryId' => $category->id,
                'active' => $family->active,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', $newName);
        $this->assertDatabaseHas('product_families', ['id' => $family->id, 'name' => $newName], 'tenant');
    }

    public function test_product_family_can_be_deleted_when_no_products(): void
    {
        $category = ProductCategory::create(['name' => 'Cat Del ' . uniqid(), 'active' => true]);
        $family = ProductFamily::create([
            'name' => 'Familia Destroy ' . uniqid(),
            'category_id' => $category->id,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/product-families/' . $family->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('product_families', ['id' => $family->id], 'tenant');
    }

    public function test_product_family_destroy_returns_400_when_has_products(): void
    {
        $this->seedCatalog();
        $family = ProductFamily::on('tenant')->first();
        $species = Species::on('tenant')->first();
        $zone = CaptureZone::on('tenant')->first();
        if (! $family || ! $species || ! $zone) {
            $this->markTestSkipped('Catalog not seeded (category, family, species, zone).');
        }
        $product = Product::create([
            'name' => 'Producto bajo familia ' . uniqid(),
            'species_id' => $species->id,
            'capture_zone_id' => $zone->id,
            'family_id' => $family->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/product-families/' . $family->id);

        $response->assertStatus(400);
        $response->assertJsonFragment(['userMessage' => 'No se puede eliminar la familia porque tiene productos asociados']);
        $this->assertDatabaseHas('product_families', ['id' => $family->id], 'tenant');
    }

    // ---------- Product ----------

    public function test_products_list_returns_paginated(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/products');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_products_options_returns_array(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/products/options');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    public function test_product_can_be_created(): void
    {
        $this->seedCatalog();
        $species = Species::on('tenant')->first();
        $zone = CaptureZone::on('tenant')->first();
        $family = ProductFamily::on('tenant')->first();
        if (! $species || ! $zone) {
            $this->markTestSkipped('Species or CaptureZone not seeded.');
        }
        $name = 'Producto Test ' . uniqid();
        $payload = [
            'name' => $name,
            'speciesId' => $species->id,
            'captureZoneId' => $zone->id,
            'familyId' => $family?->id,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/products', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'name']]);
        $response->assertJsonPath('data.name', $name);
        $this->assertDatabaseHas('products', ['name' => $name], 'tenant');
    }

    public function test_product_can_be_shown(): void
    {
        $this->seedCatalog();
        $species = Species::on('tenant')->first();
        $zone = CaptureZone::on('tenant')->first();
        $family = ProductFamily::on('tenant')->first();
        if (! $species || ! $zone) {
            $this->markTestSkipped('Species or CaptureZone not seeded.');
        }
        $product = Product::create([
            'name' => 'Producto Show ' . uniqid(),
            'species_id' => $species->id,
            'capture_zone_id' => $zone->id,
            'family_id' => $family?->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/products/' . $product->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $product->id);
        $response->assertJsonPath('data.name', $product->name);
    }

    public function test_product_can_be_updated(): void
    {
        $this->seedCatalog();
        $species = Species::on('tenant')->first();
        $zone = CaptureZone::on('tenant')->first();
        $family = ProductFamily::on('tenant')->first();
        if (! $species || ! $zone) {
            $this->markTestSkipped('Species or CaptureZone not seeded.');
        }
        $product = Product::create([
            'name' => 'Producto Update ' . uniqid(),
            'species_id' => $species->id,
            'capture_zone_id' => $zone->id,
            'family_id' => $family?->id,
        ]);
        $newName = 'Producto Actualizado ' . uniqid();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/products/' . $product->id, [
                'name' => $newName,
                'speciesId' => $product->species_id,
                'captureZoneId' => $product->capture_zone_id,
                'familyId' => $product->family_id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', $newName);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => $newName], 'tenant');
    }

    public function test_product_can_be_deleted_when_not_in_use(): void
    {
        $this->seedCatalog();
        $species = Species::on('tenant')->first();
        $zone = CaptureZone::on('tenant')->first();
        $family = ProductFamily::on('tenant')->first();
        if (! $species || ! $zone) {
            $this->markTestSkipped('Species or CaptureZone not seeded.');
        }
        $product = Product::create([
            'name' => 'Producto Destroy ' . uniqid(),
            'species_id' => $species->id,
            'capture_zone_id' => $zone->id,
            'family_id' => $family?->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/products/' . $product->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $product->id], 'tenant');
    }

    public function test_product_destroy_returns_400_when_in_use(): void
    {
        $this->seedCatalog();
        $species = Species::on('tenant')->first();
        $zone = CaptureZone::on('tenant')->first();
        $family = ProductFamily::on('tenant')->first();
        if (! $species || ! $zone) {
            $this->markTestSkipped('Species or CaptureZone not seeded.');
        }
        $product = Product::create([
            'name' => 'Producto En Uso ' . uniqid(),
            'species_id' => $species->id,
            'capture_zone_id' => $zone->id,
            'family_id' => $family?->id,
        ]);
        \App\Models\Box::create([
            'article_id' => $product->id,
            'lot' => 'LOT-TEST',
            'gs1_128' => '',
            'gross_weight' => 10,
            'net_weight' => 10,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/products/' . $product->id);

        $response->assertStatus(400);
        $response->assertJsonFragment(['userMessage' => 'No se puede eliminar el producto porque está siendo utilizado en: cajas']);
        $this->assertDatabaseHas('products', ['id' => $product->id], 'tenant');
    }

    public function test_products_destroy_multiple(): void
    {
        $this->seedCatalog();
        $species = Species::on('tenant')->first();
        $zone = CaptureZone::on('tenant')->first();
        $family = ProductFamily::on('tenant')->first();
        if (! $species || ! $zone) {
            $this->markTestSkipped('Species or CaptureZone not seeded.');
        }
        $p1 = Product::create([
            'name' => 'Producto Multi 1 ' . uniqid(),
            'species_id' => $species->id,
            'capture_zone_id' => $zone->id,
            'family_id' => $family?->id,
        ]);
        $p2 = Product::create([
            'name' => 'Producto Multi 2 ' . uniqid(),
            'species_id' => $species->id,
            'capture_zone_id' => $zone->id,
            'family_id' => $family?->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/products', ['ids' => [$p1->id, $p2->id]]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'userMessage', 'deletedCount', 'errors']);
        $this->assertGreaterThanOrEqual(1, $response->json('deletedCount'));
        $this->assertDatabaseMissing('products', ['id' => $p1->id], 'tenant');
        $this->assertDatabaseMissing('products', ['id' => $p2->id], 'tenant');
    }

    public function test_productos_endpoints_require_auth(): void
    {
        $response = $this->getJson('/api/v2/products', [
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenantSubdomain,
        ]);
        $response->assertStatus(401);
    }
}
