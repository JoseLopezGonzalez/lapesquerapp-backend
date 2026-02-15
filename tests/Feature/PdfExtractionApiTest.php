<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for A.18 Utilidades — Extracción de texto desde PDF.
 */
class PdfExtractionApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private ?string $token = null;
    private ?string $tenantSubdomain = null;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $this->createTenantAndUser();
    }

    private function createTenantAndUser(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'pdf-extract-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant PdfExtract',
            'subdomain' => $slug,
            'database' => $database,
            'active' => true,
        ]);

        $user = User::create([
            'name' => 'Test User PdfExtract',
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

    public function test_extract_requires_authentication(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->postJson('/api/v2/pdf-extract', ['pdf' => $file]);

        $response->assertUnauthorized();
    }

    public function test_extract_returns_422_when_no_file(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/pdf-extract');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pdf']);
    }

    public function test_extract_returns_422_when_invalid_mime(): void
    {
        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');
        $response = $this->withHeaders($this->authHeaders())
            ->post('/api/v2/pdf-extract', [
                'pdf' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Tenant' => $this->tenantSubdomain,
                'Authorization' => 'Bearer ' . $this->token,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pdf']);
    }

    public function test_extract_returns_422_when_unparseable_pdf(): void
    {
        $file = UploadedFile::fake()->createWithContent('invalid.pdf', 'not a valid pdf content', 'application/pdf');
        $response = $this->withHeaders($this->authHeaders())
            ->post('/api/v2/pdf-extract', [
                'pdf' => $file,
            ], [
                'Accept' => 'application/json',
                'X-Tenant' => $this->tenantSubdomain,
                'Authorization' => 'Bearer ' . $this->token,
            ]);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'No se pudo procesar el archivo PDF.',
                'userMessage' => 'El archivo PDF no pudo ser leído. Verifique que sea un PDF válido.',
            ]);
    }

}
