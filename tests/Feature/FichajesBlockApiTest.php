<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\PunchEvent;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for A.11 Fichajes: punches, employees.
 */
class FichajesBlockApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private ?string $token = null;
    private ?string $tenantSubdomain = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantConnection();
        $this->createTenantAndUser();
    }

    private function createTenantAndUser(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'fichajes-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Fichajes',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Test User Fichajes',
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

    public function test_punches_index_returns_paginated(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/punches');

        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_punches_dashboard_returns_data(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/punches/dashboard');

        $response->assertOk();
    }

    public function test_punches_calendar_returns_data(): void
    {
        $year = now()->year;
        $month = now()->month;
        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/punches/calendar?year={$year}&month={$month}");

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_punches_statistics_returns_data(): void
    {
        $dateStart = now()->startOfMonth()->format('Y-m-d');
        $dateEnd = now()->endOfMonth()->format('Y-m-d');
        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/punches/statistics?date_start={$dateStart}&date_end={$dateEnd}");

        $response->assertOk();
    }

    public function test_employees_list_returns_paginated(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/employees');

        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_employees_store_creates_employee(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/employees', [
                'name' => 'Empleado Test ' . uniqid(),
                'nfc_uid' => 'TEST-' . uniqid(),
            ]);

        $response->assertCreated()->assertJsonStructure(['message', 'data' => ['id', 'name']]);
    }

    public function test_punches_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/punches');

        $response->assertUnauthorized();
    }

    public function test_employees_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/employees');

        $response->assertUnauthorized();
    }

    /**
     * Cada día se trata por separado: si ayer el empleado quedó con entrada sin salida,
     * el primer fichaje de hoy debe ser entrada (IN), no salida.
     */
    public function test_first_punch_of_day_is_entrada_when_previous_day_has_unclosed_entrada(): void
    {
        $createEmployee = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/employees', [
                'name' => 'Empleado Fichaje Día',
                'nfc_uid' => 'NFC-' . uniqid(),
            ]);
        $createEmployee->assertCreated();
        $employeeId = $createEmployee->json('data.id');

        $yesterday = Carbon::yesterday(config('app.business_timezone', 'Europe/Madrid'))->setTime(8, 0, 0)->utc();
        PunchEvent::create([
            'employee_id' => $employeeId,
            'event_type' => PunchEvent::TYPE_IN,
            'device_id' => 'test-device',
            'timestamp' => $yesterday,
        ]);

        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->postJson('/api/v2/punches', [
            'employee_id' => $employeeId,
            'device_id' => 'test-nfc',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.event_type', 'IN');
    }

    /**
     * Manual punch with UTC timestamp is stored and returned as ISO 8601 with timezone.
     */
    public function test_manual_punch_accepts_utc_timestamp_and_returns_iso8601_with_zone(): void
    {
        $createEmployee = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/employees', [
                'name' => 'Empleado UTC Test',
                'nfc_uid' => 'NFC-UTC-' . uniqid(),
            ]);
        $createEmployee->assertCreated();
        $employeeId = $createEmployee->json('data.id');

        $utcTimestamp = '2024-06-15T09:30:00.000Z';
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/punches', [
                'employee_id' => $employeeId,
                'event_type' => PunchEvent::TYPE_IN,
                'timestamp' => $utcTimestamp,
                'device_id' => 'manual-test',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.event_type', 'IN');
        $returnedTimestamp = $response->json('data.timestamp');
        $this->assertNotEmpty($returnedTimestamp);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $returnedTimestamp, 'Timestamp should be ISO 8601');
        $this->assertTrue(str_contains($returnedTimestamp, 'Z') || preg_match('/[+-]\d{2}:\d{2}$/', $returnedTimestamp), 'Timestamp should include timezone (Z or offset)');

        $id = $response->json('data.id');
        $getResponse = $this->withHeaders($this->authHeaders())->getJson("/api/v2/punches/{$id}");
        $getResponse->assertOk();
        $getTimestamp = $getResponse->json('data.timestamp');
        $this->assertNotEmpty($getTimestamp);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $getTimestamp);
    }
}
