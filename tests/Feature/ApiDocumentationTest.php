<?php

namespace Tests\Feature;

use Knuckles\Scribe\ScribeServiceProvider;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Scribe's GenerateDocumentation command calls PHP's exit() directly when a route's
        // ResponseCall fails (see vendor/knuckleswtf/scribe), which would kill the whole
        // PHPUnit process. Setting this env var makes Scribe return normally instead, so a
        // route-level regression surfaces as a failing assertion here rather than a crashed run.
        $_SERVER['SCRIBE_TESTS'] = '1';

        // ScribeServiceProvider::$customTranslationLayerLoaded is a *static* flag: once set by
        // the first scribe:generate call in this process, it survives Laravel's per-test
        // refreshApplication() and makes the translator of the next test's (fresh) app instance
        // think its custom lang layer is already loaded when it isn't, breaking `trans()`
        // lookups for scribe's own strings. Reset it before each test so the two scribe:generate
        // calls in this class (internal + public config) don't bleed state into each other. This
        // is purely a test-process artifact: real usage (contract:publish/contract:check) always
        // shells out to a fresh `php artisan` process, which never hits this.
        ScribeServiceProvider::$customTranslationLayerLoaded = false;
    }

    protected function tearDown(): void
    {
        unset($_SERVER['SCRIBE_TESTS']);

        parent::tearDown();
    }

    public function test_scribe_generate_succeeds(): void
    {
        $this->artisan('scribe:generate')
            ->assertExitCode(0);
    }

    /**
     * @depends test_scribe_generate_succeeds
     */
    public function test_openapi_spec_exists(): void
    {
        $this->assertFileExists(public_path('docs/openapi.yaml'));
        $this->assertFileExists(public_path('docs/index.html'));
        $this->assertFileExists(public_path('docs/collection.json'));
    }

    /**
     * @depends test_scribe_generate_succeeds
     */
    public function test_openapi_spec_includes_tenant_header(): void
    {
        $openapi = file_get_contents(public_path('docs/openapi.yaml'));

        $this->assertStringContainsString('X-Tenant', $openapi);
    }

    /**
     * @depends test_scribe_generate_succeeds
     */
    public function test_openapi_spec_includes_auth_scheme(): void
    {
        $openapi = file_get_contents(public_path('docs/openapi.yaml'));

        $this->assertMatchesRegularExpression('/bearer|Bearer|Authorization/i', $openapi);
    }

    public function test_public_scribe_generate_succeeds(): void
    {
        $this->artisan('scribe:generate', ['--config' => 'scribe_public'])
            ->assertExitCode(0);
    }

    /**
     * @depends test_public_scribe_generate_succeeds
     */
    public function test_public_openapi_spec_exists(): void
    {
        $this->assertFileExists(base_path('storage/app/scribe-public/openapi.yaml'));
    }

    /**
     * Contrato frontend: no debe filtrar nunca rutas de superadmin, impersonación interna
     * ni observabilidad. Si este test falla, algo se ha vuelto a registrar bajo esos
     * prefijos sin excluirlo explícitamente en config/scribe_public.php.
     *
     * @depends test_public_scribe_generate_succeeds
     */
    public function test_public_openapi_spec_excludes_sensitive_routes(): void
    {
        $openapi = file_get_contents(base_path('storage/app/scribe-public/openapi.yaml'));

        $this->assertStringNotContainsString('/superadmin', $openapi);
        $this->assertStringNotContainsString('/public/impersonation', $openapi);
        $this->assertStringNotContainsString('impersonate', $openapi);
    }

    /**
     * Sanity check inverso al anterior: si la exclusión fuera demasiado agresiva, el
     * contrato frontend quedaría vacío de rutas de negocio sin que nadie lo notara.
     *
     * @depends test_public_scribe_generate_succeeds
     */
    public function test_public_openapi_spec_includes_business_routes(): void
    {
        $openapi = file_get_contents(base_path('storage/app/scribe-public/openapi.yaml'));

        $this->assertStringContainsString('/orders', $openapi);
        $this->assertStringContainsString('/v2/public/tenant', $openapi);
    }
}
