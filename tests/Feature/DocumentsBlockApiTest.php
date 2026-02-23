<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Incoterm;
use App\Models\Order;
use App\Models\PaymentTerm;
use App\Models\Salesperson;
use App\Models\Tenant;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for A.15 Documentos: OrderDocumentController, PDF, Excel.
 */
class DocumentsBlockApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private ?string $token = null;
    private ?string $tenantSubdomain = null;
    private ?Order $order = null;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $this->createTenantUserAndOrder();
    }

    private function createTenantUserAndOrder(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'docs-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Docs',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Test User Docs',
            'email' => $slug . '@test.com',
            'role' => Role::Administrador->value,
            'active' => true,
        ]);

        $this->token = $user->createToken('test')->plainTextToken;
        $this->tenantSubdomain = $slug;

        $country = Country::firstOrCreate(['name' => 'España']);
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado']);
        $transport = Transport::firstOrCreate(
            ['name' => 'Transport Docs ' . uniqid()],
            ['vat_number' => 'B' . uniqid(), 'address' => 'A', 'emails' => 't@t.com']
        );
        $salesperson = Salesperson::firstOrCreate(['name' => 'Comercial Docs']);
        $incoterm = Incoterm::firstOrCreate(
            ['code' => 'EXW'],
            ['description' => 'Ex Works']
        );
        $customer = Customer::create([
            'name' => 'C Docs ' . uniqid(),
            'vat_number' => 'B' . uniqid(),
            'payment_term_id' => $paymentTerm->id,
            'billing_address' => 'B',
            'shipping_address' => 'S',
            'salesperson_id' => $salesperson->id,
            'emails' => 'c@c.com',
            'contact_info' => 'C',
            'country_id' => $country->id,
            'transport_id' => $transport->id,
        ]);

        $this->order = Order::create([
            'customer_id' => $customer->id,
            'entry_date' => now()->format('Y-m-d'),
            'load_date' => now()->addDay()->format('Y-m-d'),
            'payment_term_id' => $paymentTerm->id,
            'salesperson_id' => $salesperson->id,
            'transport_id' => $transport->id,
            'incoterm_id' => $incoterm->id,
            'billing_address' => 'B',
            'shipping_address' => 'S',
            'emails' => 'test@test.com',
            'status' => 'pending',
            'buyer_reference' => 'REF-DOCS',
        ]);
    }

    private function authHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_send_custom_documentation_returns_200_with_valid_payload(): void
    {
        if (! file_exists('/usr/bin/google-chrome') && ! file_exists('/usr/bin/chromium')) {
            $this->markTestSkipped('Chromium not available for PDF generation');
        }

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/send-custom-documents", [
                'documents' => [
                    [
                        'type' => 'loading-note',
                        'recipients' => ['customer'],
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Documentación enviada correctamente.']);
    }

    public function test_send_custom_documentation_returns_422_with_invalid_payload(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/send-custom-documents", [
                'documents' => [],
            ]);

        $response->assertUnprocessable();
    }

    public function test_send_custom_documentation_returns_422_with_invalid_document_type(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/send-custom-documents", [
                'documents' => [
                    [
                        'type' => 'invalid-type',
                        'recipients' => ['customer'],
                    ],
                ],
            ]);

        $response->assertUnprocessable();
    }

    public function test_send_standard_documentation_returns_200(): void
    {
        if (! file_exists('/usr/bin/google-chrome') && ! file_exists('/usr/bin/chromium')) {
            $this->markTestSkipped('Chromium not available for PDF generation');
        }

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/send-standard-documents");

        $response->assertOk()
            ->assertJson(['message' => 'Documentación estándar enviada correctamente.']);
    }

    public function test_send_custom_documentation_returns_401_without_auth(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->postJson("/api/v2/orders/{$this->order->id}/send-custom-documents", [
                'documents' => [
                    ['type' => 'loading-note', 'recipients' => ['customer']],
                ],
            ]);

        $response->assertUnauthorized();
    }

    public function test_send_standard_documentation_returns_401_without_auth(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->postJson("/api/v2/orders/{$this->order->id}/send-standard-documents");

        $response->assertUnauthorized();
    }

    public function test_export_product_lot_details_returns_200_with_auth(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->get("/api/v2/orders/{$this->order->id}/xlsx/lots-report");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_product_lot_details_returns_401_without_auth(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->get("/api/v2/orders/{$this->order->id}/xlsx/lots-report");

        $response->assertUnauthorized();
    }

}
