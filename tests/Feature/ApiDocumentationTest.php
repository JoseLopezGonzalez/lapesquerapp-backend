<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
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
}
