<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttachmentPathGenerator
{
    /**
     * Genera la ruta relativa dentro del disco para un adjunto.
     *
     * Formato: tenants/{slug}/pallets/{id}/{collection}/{ulid}.{ext}
     */
    public function generate(Model $attachable, string $collection, string $extension): string
    {
        $tenant = app('currentTenant');
        $morphKey = $this->morphKey($attachable);
        $ulid = Str::ulid();
        $ext = ltrim(strtolower($extension), '.');

        return "tenants/{$tenant}/{$morphKey}s/{$attachable->id}/{$collection}/{$ulid}.{$ext}";
    }

    public function storedName(string $path): string
    {
        return basename($path);
    }

    private function morphKey(Model $attachable): string
    {
        // Devuelve la clave corta registrada en el morphMap.
        $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        $class = get_class($attachable);

        return array_search($class, $morphMap, true) ?: class_basename($class);
    }
}
