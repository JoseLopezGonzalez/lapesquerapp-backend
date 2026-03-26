<?php

namespace App\Support;

class TenantSeedDataset
{
    public const BASE = 'base';

    public const EXTENDED = 'extended';

    public const EDGE = 'edge';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::BASE,
            self::EXTENDED,
            self::EDGE,
        ];
    }

    public static function normalize(?string $dataset): string
    {
        $value = strtolower(trim((string) $dataset));

        return match ($value) {
            '', 'default', 'standard' => self::BASE,
            'full', 'extended', 'demo' => self::EXTENDED,
            'edge', 'edge-cases', 'qa' => self::EDGE,
            default => $value,
        };
    }

    public static function isValid(?string $dataset): bool
    {
        return in_array(self::normalize($dataset), self::values(), true);
    }

    public static function seederClassFor(?string $dataset): string
    {
        return match (self::normalize($dataset)) {
            self::EXTENDED => 'TenantExtendedDatasetSeeder',
            self::EDGE => 'TenantEdgeDatasetSeeder',
            default => 'TenantDatabaseSeeder',
        };
    }
}
