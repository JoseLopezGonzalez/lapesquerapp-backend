<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        try {
            Log::info('🟣 [STORE RESOURCE] toArray iniciado', [
                'store_id' => $this->id ?? 'N/A',
                'store_name' => $this->name ?? 'N/A'
            ]);

            Log::info('🟣 [STORE RESOURCE] Llamando toArrayAssoc()');
            $result = $this->toArrayAssoc();
            Log::info('🟣 [STORE RESOURCE] toArrayAssoc() completado exitosamente', [
                'store_id' => $this->id ?? 'N/A',
                'has_result' => !empty($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('🔴 [STORE RESOURCE] Error en toArray', [
                'store_id' => $this->id ?? 'N/A',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
