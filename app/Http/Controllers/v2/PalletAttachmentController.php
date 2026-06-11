<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\StoreAttachmentRequest;
use App\Http\Requests\v2\UpdateAttachmentRequest;
use App\Http\Resources\v2\AttachmentResource;
use App\Models\Attachment;
use App\Models\Pallet;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PalletAttachmentController extends Controller
{
    public function __construct(private AttachmentService $service) {}

    public function index(Pallet $pallet, Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Attachment::class, $pallet]);

        $collection = $request->query('collection');
        $perPage = (int) $request->query('per_page', 20);

        return AttachmentResource::collection(
            $this->service->list($pallet, $collection ?: null, $perPage)
        )->response();
    }

    public function store(Pallet $pallet, StoreAttachmentRequest $request): JsonResponse
    {
        $this->authorize('create', [Attachment::class, $pallet]);

        try {
            $attachment = $this->service->store(
                $pallet,
                $request->file('file'),
                $request->validated('collection'),
                $request->user(),
                $request->only(['notes', 'metadata'])
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'No se pudo adjuntar el archivo.',
                'userMessage' => $e->getMessage(),
            ], 422);
        }

        return response()->json(new AttachmentResource($attachment->load('uploadedBy')), 201);
    }

    public function show(Pallet $pallet, Attachment $attachment): JsonResponse
    {
        $this->authorize('view', $attachment);

        return response()->json(['data' => new AttachmentResource($attachment->load('uploadedBy'))]);
    }

    public function update(Pallet $pallet, Attachment $attachment, UpdateAttachmentRequest $request): JsonResponse
    {
        $this->authorize('update', $attachment);

        $attachment = $this->service->update($attachment, $request->validated());

        return response()->json(['data' => new AttachmentResource($attachment->load('uploadedBy'))]);
    }

    public function download(Pallet $pallet, Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        return $this->service->download($attachment);
    }

    public function thumbnail(Pallet $pallet, Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        return $this->service->thumbnail($attachment);
    }

    public function destroy(Pallet $pallet, Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        $this->service->delete($attachment);

        return response()->json(null, 204);
    }
}
