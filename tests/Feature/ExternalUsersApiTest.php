<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Box;
use App\Models\ExternalUser;
use App\Models\MagicLinkToken;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoredPallet;
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

class ExternalUsersApiTest extends TestCase
{
    use ConfiguresTenantConnection;
    use RefreshDatabase;

    private ?string $tenantSubdomain = null;

    private ?User $admin = null;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $this->createTenantAndAdmin();
    }

    private function createTenantAndAdmin(): void
    {
        $database = config('database.connections.'.config('database.default').'.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'external-'.uniqid();

        Tenant::create([
            'name' => 'Test Tenant External',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin External',
            'email' => $slug.'@test.com',
            'role' => Role::Administrador->value,
            'active' => true,
        ]);

        $this->tenantSubdomain = $slug;
        app()->instance('currentTenant', $slug);
    }

    private function authHeadersFor(User|ExternalUser $actor): array
    {
        $token = $actor->createToken('test')->plainTextToken;

        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    private function jsonHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ];
    }

    private function seedCatalogs(): void
    {
        (new ProductCategorySeeder)->run();
        (new ProductFamilySeeder)->run();
        (new CaptureZonesSeeder)->run();
        (new FishingGearSeeder)->run();
        (new SpeciesSeeder)->run();

        if (Product::count() === 0) {
            Product::create([
                'name' => 'Producto Externo Test',
                'species_id' => \App\Models\Species::first()->id,
                'capture_zone_id' => \App\Models\CaptureZone::first()->id,
                'family_id' => \App\Models\ProductFamily::first()->id,
                'article_gtin' => null,
                'box_gtin' => null,
                'pallet_gtin' => null,
            ]);
        }
    }

    private function createExternalUser(?string $email = null, bool $active = true): ExternalUser
    {
        return ExternalUser::create([
            'name' => 'Maquilador Test',
            'company_name' => 'Proveedor Test',
            'email' => $email ?? ('external-'.uniqid().'@test.com'),
            'type' => ExternalUser::TYPE_MAQUILADOR,
            'is_active' => $active,
            'notes' => 'Notas test',
        ]);
    }

    private function createExternalStore(ExternalUser $externalUser, ?string $name = null): Store
    {
        return Store::create([
            'name' => $name ?? ('Store externo '.uniqid()),
            'temperature' => -18,
            'capacity' => 1000,
            'map' => null,
            'store_type' => 'externo',
            'external_user_id' => $externalUser->id,
        ]);
    }

    private function createStoredPallet(Store $store): Pallet
    {
        $this->seedCatalogs();
        $product = Product::first();

        $pallet = Pallet::create([
            'observations' => 'Pallet externo',
            'status' => Pallet::STATE_STORED,
        ]);

        $box = Box::create([
            'article_id' => $product->id,
            'lot' => 'LOT-'.uniqid(),
            'gs1_128' => 'GS1-'.uniqid(),
            'gross_weight' => 10.0,
            'net_weight' => 9.0,
        ]);

        \App\Models\PalletBox::create([
            'pallet_id' => $pallet->id,
            'box_id' => $box->id,
        ]);

        StoredPallet::create([
            'pallet_id' => $pallet->id,
            'store_id' => $store->id,
        ]);

        return $pallet->fresh();
    }

    public function test_admin_can_crud_external_users_and_validate_duplicate_internal_email(): void
    {
        $response = $this->withHeaders($this->authHeadersFor($this->admin))
            ->postJson('/api/v2/external-users', [
                'name' => 'Maquilador Uno',
                'company_name' => 'Proveedor Uno',
                'email' => 'maquilador-uno@test.com',
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'maquilador-uno@test.com')
            ->assertJsonPath('data.type', ExternalUser::TYPE_MAQUILADOR);

        $duplicate = $this->withHeaders($this->authHeadersFor($this->admin))
            ->postJson('/api/v2/external-users', [
                'name' => 'Duplicado',
                'email' => $this->admin->email,
            ]);

        $duplicate->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $externalUserId = $response->json('data.id');
        $update = $this->withHeaders($this->authHeadersFor($this->admin))
            ->putJson('/api/v2/external-users/'.$externalUserId, [
                'notes' => 'Actualizado',
            ]);

        $update->assertStatus(200)
            ->assertJsonPath('data.notes', 'Actualizado');

        $this->withHeaders($this->authHeadersFor($this->admin))
            ->postJson('/api/v2/external-users/'.$externalUserId.'/deactivate')
            ->assertStatus(200)
            ->assertJsonPath('data.isActive', false);

        $this->assertDatabaseHas('external_users', [
            'id' => $externalUserId,
            'is_active' => false,
        ], 'tenant');
    }

    public function test_request_access_and_verify_otp_work_for_external_user(): void
    {
        $externalUser = $this->createExternalUser(email: 'external-login@test.com');

        $requestAccess = $this->withHeaders($this->jsonHeaders())
            ->postJson('/api/v2/auth/request-access', [
                'email' => $externalUser->email,
            ]);

        $requestAccess->assertStatus(200);
        $this->assertDatabaseHas('magic_link_tokens', [
            'email' => $externalUser->email,
            'type' => MagicLinkToken::TYPE_OTP,
        ], 'tenant');

        $otp = MagicLinkToken::where('email', $externalUser->email)
            ->where('type', MagicLinkToken::TYPE_OTP)
            ->latest('id')
            ->first();

        $verify = $this->withHeaders($this->jsonHeaders())
            ->postJson('/api/v2/auth/otp/verify', [
                'email' => $externalUser->email,
                'code' => $otp->otp_code,
            ]);

        $verify->assertStatus(200)
            ->assertJsonPath('user.actorType', 'external_user')
            ->assertJsonPath('user.externalUserType', ExternalUser::TYPE_MAQUILADOR)
            ->assertJsonPath('user.role', null);
    }

    public function test_external_user_me_returns_actor_shape_and_blocks_inactive_access(): void
    {
        $externalUser = $this->createExternalUser();
        $store = $this->createExternalStore($externalUser);

        $me = $this->withHeaders($this->authHeadersFor($externalUser))
            ->getJson('/api/v2/me');

        $me->assertStatus(200)
            ->assertJsonPath('actorType', 'external_user')
            ->assertJsonPath('externalUserType', ExternalUser::TYPE_MAQUILADOR)
            ->assertJsonPath('allowedStoreIds.0', $store->id);

        $externalUser->update(['is_active' => false]);

        $blocked = $this->withHeaders($this->authHeadersFor($externalUser->fresh()))
            ->getJson('/api/v2/me');

        $blocked->assertStatus(403);
    }

    public function test_external_user_only_sees_own_stores_and_cannot_access_admin_routes(): void
    {
        $externalUser = $this->createExternalUser();
        $ownedStore = $this->createExternalStore($externalUser, 'Store propio');
        $otherExternal = $this->createExternalUser();
        $foreignStore = $this->createExternalStore($otherExternal, 'Store ajeno');

        $list = $this->withHeaders($this->authHeadersFor($externalUser))
            ->getJson('/api/v2/stores');

        $list->assertStatus(200);
        $this->assertCount(1, $list->json('data'));
        $this->assertSame($ownedStore->id, $list->json('data.0.id'));

        $forbiddenStore = $this->withHeaders($this->authHeadersFor($externalUser))
            ->getJson('/api/v2/stores/'.$foreignStore->id);
        $forbiddenStore->assertStatus(403);

        $adminRoute = $this->withHeaders($this->authHeadersFor($externalUser))
            ->getJson('/api/v2/users');
        $adminRoute->assertStatus(403);
    }

    public function test_external_user_can_create_update_and_move_pallets_only_within_own_stores(): void
    {
        $externalUser = $this->createExternalUser();
        $sourceStore = $this->createExternalStore($externalUser, 'Origen');
        $targetStore = $this->createExternalStore($externalUser, 'Destino');
        $foreignUser = $this->createExternalUser();
        $foreignStore = $this->createExternalStore($foreignUser, 'Ajeno');

        $this->seedCatalogs();
        $product = Product::first();

        $create = $this->withHeaders($this->authHeadersFor($externalUser))
            ->postJson('/api/v2/pallets', [
                'observations' => 'Creado por externo',
                'store' => ['id' => $sourceStore->id],
                'boxes' => [
                    [
                        'product' => ['id' => $product->id],
                        'lot' => 'LOT-CREATE',
                        'gs1128' => 'GS1-CREATE',
                        'grossWeight' => 10.0,
                        'netWeight' => 9.0,
                    ],
                ],
            ]);

        $create->assertStatus(201);
        $palletId = $create->json('id');

        $update = $this->withHeaders($this->authHeadersFor($externalUser))
            ->putJson('/api/v2/pallets/'.$palletId, [
                'id' => $palletId,
                'observations' => 'Actualizado por externo',
            ]);

        $update->assertStatus(201)
            ->assertJsonPath('observations', 'Actualizado por externo');

        $move = $this->withHeaders($this->authHeadersFor($externalUser))
            ->postJson('/api/v2/pallets/move-to-store', [
                'pallet_id' => $palletId,
                'store_id' => $targetStore->id,
            ]);

        $move->assertStatus(200);

        $forbiddenMove = $this->withHeaders($this->authHeadersFor($externalUser))
            ->postJson('/api/v2/pallets/move-to-store', [
                'pallet_id' => $palletId,
                'store_id' => $foreignStore->id,
            ]);

        $forbiddenMove->assertStatus(422);

        $delete = $this->withHeaders($this->authHeadersFor($externalUser))
            ->deleteJson('/api/v2/pallets/'.$palletId);
        $delete->assertStatus(403);
    }

    public function test_deactivating_or_deleting_external_user_revokes_tokens(): void
    {
        $externalUser = $this->createExternalUser();
        $externalUser->createToken('device');
        $accessToken = $externalUser->tokens()->latest('id')->first();
        $this->assertNotNull($accessToken);

        $this->withHeaders($this->authHeadersFor($this->admin))
            ->postJson('/api/v2/external-users/'.$externalUser->id.'/deactivate')
            ->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $accessToken->id,
        ], 'tenant');

        $externalUser = $this->createExternalUser(email: 'delete-me@test.com');
        $externalUser->createToken('device');
        $deleteAccessToken = $externalUser->tokens()->latest('id')->first();
        $this->assertNotNull($deleteAccessToken);

        $this->withHeaders($this->authHeadersFor($this->admin))
            ->deleteJson('/api/v2/external-users/'.$externalUser->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('external_users', ['id' => $externalUser->id], 'tenant');
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $deleteAccessToken->id,
        ], 'tenant');
    }

    public function test_activating_inactive_external_user_resends_access(): void
    {
        $externalUser = $this->createExternalUser(email: 'reactivate@test.com', active: false);

        $this->withHeaders($this->authHeadersFor($this->admin))
            ->postJson('/api/v2/external-users/'.$externalUser->id.'/activate')
            ->assertStatus(200)
            ->assertJsonPath('data.isActive', true);

        $this->assertDatabaseHas('magic_link_tokens', [
            'email' => $externalUser->email,
            'type' => MagicLinkToken::TYPE_MAGIC_LINK,
        ], 'tenant');
        $this->assertDatabaseHas('magic_link_tokens', [
            'email' => $externalUser->email,
            'type' => MagicLinkToken::TYPE_OTP,
        ], 'tenant');
    }
}
