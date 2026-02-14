<?php

namespace Tests\Unit\Services;

use App\Services\v2\OrderListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderListServiceTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
    }

    public function test_options_returns_collection(): void
    {
        $result = OrderListService::options();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_active_returns_collection(): void
    {
        $result = OrderListService::active();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_list_with_active_true_returns_collection(): void
    {
        $request = Request::create('/orders', 'GET', ['active' => 'true']);
        $result = OrderListService::list($request);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_list_without_active_returns_paginator(): void
    {
        $request = Request::create('/orders', 'GET', []);
        $result = OrderListService::list($request);
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
    }
}
