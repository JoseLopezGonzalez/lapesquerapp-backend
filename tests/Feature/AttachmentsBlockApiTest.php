<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pallet;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests para el sistema de adjuntos — Fase 1+2 (piloto: Pallet).
 * Cubre: subida válida, MIME inválido, listado paginado, filtro por colección,
 * detalle, actualización de notas, descarga, borrado y denegación por permisos.
 */
class AttachmentsBlockApiTest extends TestCase
{
    use ConfiguresTenantConnection;
    use RefreshDatabase;

    private User $admin;

    private User $operario;

    private Pallet $pallet;

    private string $tenantSubdomain;

    private string $adminToken;

    private string $operarioToken;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        Storage::fake('attachments');
        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        $slug = 'att-'.uniqid();
        $db = config('database.connections.'.config('database.default').'.database') ?? env('DB_DATABASE', 'testing');

        Tenant::create([
            'name' => 'Test Tenant Attachments',
            'subdomain' => $slug,
            'database' => $db,
            'status' => 'active',
        ]);

        $this->tenantSubdomain = $slug;

        $this->admin = User::create([
            'name' => 'Admin Att',
            'email' => "admin-{$slug}@test.com",
            'role' => Role::Administrador->value,
            'active' => true,
        ]);

        $this->operario = User::create([
            'name' => 'Operario Att',
            'email' => "operario-{$slug}@test.com",
            'role' => Role::Operario->value,
            'active' => true,
        ]);

        $this->pallet = Pallet::create([
            'observations' => 'Palet test',
            'status' => Pallet::STATE_STORED,
        ]);

        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->operarioToken = $this->operario->createToken('test')->plainTextToken;
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function headers(string $token): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }

    private function fakeJpeg(string $name = 'foto.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 400, 300);
    }

    private function fakePdf(string $name = 'doc.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    private function palletUrl(): string
    {
        return "/api/v2/pallets/{$this->pallet->id}/attachments";
    }

    // ─── subida ──────────────────────────────────────────────────────────────

    public function test_admin_puede_subir_imagen_valida(): void
    {
        $response = $this->withHeaders($this->headers($this->adminToken))
            ->post($this->palletUrl(), [
                'file' => $this->fakeJpeg(),
                'collection' => 'pallet_image',
                'notes' => 'Foto de prueba',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('collection', 'pallet_image')
            ->assertJsonPath('notes', 'Foto de prueba')
            ->assertJsonPath('mimeType', 'image/jpeg');

        $this->assertDatabaseHas('attachments', [
            'collection' => 'pallet_image',
            'attachable_id' => $this->pallet->id,
        ], 'tenant');
    }

    public function test_operario_puede_subir_imagen(): void
    {
        $response = $this->withHeaders($this->headers($this->operarioToken))
            ->post($this->palletUrl(), [
                'file' => $this->fakeJpeg(),
                'collection' => 'pallet_image',
            ]);

        $response->assertStatus(201);
    }

    public function test_subida_rechaza_mime_invalido(): void
    {
        $response = $this->withHeaders($this->headers($this->adminToken))
            ->post($this->palletUrl(), [
                'file' => $this->fakePdf(),
                'collection' => 'pallet_image',
            ]);

        // El servicio lanza InvalidArgumentException → el controller responde 422
        $response->assertStatus(422)
            ->assertJsonPath('userMessage', fn ($v) => str_contains($v, 'no permitido'));
    }

    public function test_subida_rechaza_coleccion_invalida(): void
    {
        $response = $this->withHeaders($this->headers($this->adminToken))
            ->post($this->palletUrl(), [
                'file' => $this->fakeJpeg(),
                'collection' => 'coleccion_inexistente',
            ]);

        $response->assertStatus(422);
    }

    public function test_subida_requiere_autenticacion(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->post($this->palletUrl(), [
                'file' => $this->fakeJpeg(),
                'collection' => 'pallet_image',
            ]);

        $response->assertStatus(401);
    }

    // ─── listado ─────────────────────────────────────────────────────────────

    public function test_listado_devuelve_paginacion(): void
    {
        // Subimos 3 imágenes
        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders($this->headers($this->adminToken))
                ->post($this->palletUrl(), [
                    'file' => $this->fakeJpeg("foto{$i}.jpg"),
                    'collection' => 'pallet_image',
                ]);
        }

        $response = $this->withHeaders($this->headers($this->adminToken))
            ->get($this->palletUrl().'?per_page=2');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_listado_filtra_por_coleccion(): void
    {
        $this->withHeaders($this->headers($this->adminToken))
            ->post($this->palletUrl(), [
                'file' => $this->fakeJpeg(),
                'collection' => 'pallet_image',
            ]);

        $response = $this->withHeaders($this->headers($this->adminToken))
            ->get($this->palletUrl().'?collection=pallet_image');

        $response->assertStatus(200);
        foreach ($response->json('data') as $item) {
            $this->assertEquals('pallet_image', $item['collection']);
        }
    }

    // ─── detalle ─────────────────────────────────────────────────────────────

    public function test_admin_puede_ver_detalle_de_adjunto(): void
    {
        $uploadResponse = $this->withHeaders($this->headers($this->adminToken))
            ->post($this->palletUrl(), [
                'file' => $this->fakeJpeg(),
                'collection' => 'pallet_image',
            ]);

        $id = $uploadResponse->json('id');

        $response = $this->withHeaders($this->headers($this->adminToken))
            ->get("{$this->palletUrl()}/{$id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $id);
    }

    // ─── actualización ───────────────────────────────────────────────────────

    public function test_admin_puede_actualizar_notas(): void
    {
        $id = $this->withHeaders($this->headers($this->adminToken))
            ->post($this->palletUrl(), [
                'file' => $this->fakeJpeg(),
                'collection' => 'pallet_image',
                'notes' => 'Nota original',
            ])->json('id');

        $response = $this->withHeaders($this->headers($this->adminToken))
            ->patch("{$this->palletUrl()}/{$id}", ['notes' => 'Nota actualizada']);

        $response->assertStatus(200)
            ->assertJsonPath('data.notes', 'Nota actualizada');
    }

    // ─── descarga ────────────────────────────────────────────────────────────

    public function test_admin_puede_descargar_adjunto(): void
    {
        $id = $this->withHeaders($this->headers($this->adminToken))
            ->post($this->palletUrl(), [
                'file' => $this->fakeJpeg(),
                'collection' => 'pallet_image',
            ])->json('id');

        $response = $this->withHeaders($this->headers($this->adminToken))
            ->get("{$this->palletUrl()}/{$id}/download");

        $response->assertStatus(200);
    }

    // ─── borrado ─────────────────────────────────────────────────────────────

    public function test_admin_puede_borrar_adjunto(): void
    {
        $id = $this->withHeaders($this->headers($this->adminToken))
            ->post($this->palletUrl(), [
                'file' => $this->fakeJpeg(),
                'collection' => 'pallet_image',
            ])->json('id');

        $response = $this->withHeaders($this->headers($this->adminToken))
            ->delete("{$this->palletUrl()}/{$id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('attachments', ['id' => $id], 'tenant');
    }

    public function test_operario_no_puede_borrar_adjunto(): void
    {
        $id = $this->withHeaders($this->headers($this->adminToken))
            ->post($this->palletUrl(), [
                'file' => $this->fakeJpeg(),
                'collection' => 'pallet_image',
            ])->json('id');

        $response = $this->withHeaders($this->headers($this->operarioToken))
            ->delete("{$this->palletUrl()}/{$id}");

        $response->assertStatus(403);
    }

    // ─── morphMap ────────────────────────────────────────────────────────────

    public function test_attachable_type_guarda_clave_corta_del_morphmap(): void
    {
        $this->withHeaders($this->headers($this->adminToken))
            ->post($this->palletUrl(), [
                'file' => $this->fakeJpeg(),
                'collection' => 'pallet_image',
            ]);

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => 'pallet',
        ], 'tenant');
    }
}
