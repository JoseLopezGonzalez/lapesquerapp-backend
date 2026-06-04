<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection' => $this->collection,
            'originalName' => $this->original_name,
            'mimeType' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'uploadedBy' => $this->whenLoaded('uploadedBy', fn () => [
                'id' => $this->uploadedBy->id,
                'name' => $this->uploadedBy->name,
            ]),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
