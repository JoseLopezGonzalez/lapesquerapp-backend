<?php

namespace Tests\Feature;

use App\Support\TenantSeedDataset;
use Tests\TestCase;

class TenantSeedDatasetSupportTest extends TestCase
{
    public function test_dataset_aliases_are_normalized_to_official_levels(): void
    {
        $this->assertSame(TenantSeedDataset::BASE, TenantSeedDataset::normalize('standard'));
        $this->assertSame(TenantSeedDataset::EXTENDED, TenantSeedDataset::normalize('demo'));
        $this->assertSame(TenantSeedDataset::EDGE, TenantSeedDataset::normalize('qa'));
    }

    public function test_dataset_names_resolve_expected_seeders(): void
    {
        $this->assertSame('TenantDatabaseSeeder', TenantSeedDataset::seederClassFor('base'));
        $this->assertSame('TenantExtendedDatasetSeeder', TenantSeedDataset::seederClassFor('extended'));
        $this->assertSame('TenantEdgeDatasetSeeder', TenantSeedDataset::seederClassFor('edge'));
    }
}
