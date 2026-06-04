<?php

namespace App\Services;

use RuntimeException;

class AttachmentCollectionRegistry
{
    public function collectionsFor(string $morphKey): array
    {
        return config("attachments.collections.{$morphKey}", []);
    }

    public function isValidCollection(string $morphKey, string $collection): bool
    {
        return array_key_exists($collection, $this->collectionsFor($morphKey));
    }

    public function allowedMimes(string $morphKey, string $collection): array
    {
        return $this->collectionsFor($morphKey)[$collection]['mimes'] ?? [];
    }

    public function maxSize(string $morphKey, string $collection): int
    {
        return $this->collectionsFor($morphKey)[$collection]['max_size'] ?? (10 * 1024 * 1024);
    }

    public function maxCount(string $morphKey, string $collection): int
    {
        return $this->collectionsFor($morphKey)[$collection]['max_count'] ?? 20;
    }

    /** Lanza excepción si la colección no existe para el morphKey dado. */
    public function assertValid(string $morphKey, string $collection): void
    {
        if (! $this->isValidCollection($morphKey, $collection)) {
            throw new RuntimeException("Colección '{$collection}' no válida para '{$morphKey}'.");
        }
    }
}
