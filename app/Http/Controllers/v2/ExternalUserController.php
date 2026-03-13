<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexExternalUserRequest;
use App\Http\Requests\v2\StoreExternalUserRequest;
use App\Http\Requests\v2\UpdateExternalUserRequest;
use App\Http\Resources\v2\ExternalUserResource;
use App\Models\ExternalUser;
use App\Services\AuthActorService;
use App\Services\MagicLinkService;
use Illuminate\Http\JsonResponse;

class ExternalUserController extends Controller
{
    public function __construct(
        protected AuthActorService $actors
    ) {}

    public function index(IndexExternalUserRequest $request)
    {
        $query = ExternalUser::query()->withCount('stores')->orderBy('name');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->input('name').'%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'like', '%'.$request->input('email').'%');
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return ExternalUserResource::collection($query->paginate($request->integer('perPage', 10)));
    }

    public function store(StoreExternalUserRequest $request)
    {
        $validated = $request->validated();
        $validated['type'] = $validated['type'] ?? ExternalUser::TYPE_MAQUILADOR;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $externalUser = ExternalUser::create($validated);

        if ($externalUser->is_active) {
            app(MagicLinkService::class)->sendMagicLinkToUser($externalUser);
        }

        return response()->json([
            'message' => 'Usuario externo creado correctamente.',
            'data' => new ExternalUserResource($externalUser->loadCount('stores')),
        ], 201);
    }

    public function show(ExternalUser $externalUser)
    {
        $this->authorize('view', $externalUser);

        return response()->json([
            'message' => 'Usuario externo obtenido correctamente.',
            'data' => new ExternalUserResource($externalUser->loadCount('stores')),
        ]);
    }

    public function update(UpdateExternalUserRequest $request, ExternalUser $externalUser)
    {
        $wasActive = $externalUser->is_active;
        $externalUser->update($request->validated());

        if ($wasActive && ! $externalUser->is_active) {
            $this->actors->revokeTokens($externalUser);
        }

        return response()->json([
            'message' => 'Usuario externo actualizado correctamente.',
            'data' => new ExternalUserResource($externalUser->fresh()->loadCount('stores')),
        ]);
    }

    public function destroy(ExternalUser $externalUser)
    {
        $this->authorize('delete', $externalUser);
        $this->actors->revokeTokens($externalUser);
        $externalUser->delete();

        return response()->json(['message' => 'Usuario externo eliminado correctamente.']);
    }

    public function resendAccess(ExternalUser $externalUser)
    {
        $this->authorize('update', $externalUser);

        if (! $externalUser->is_active) {
            return response()->json([
                'message' => 'Acción no autorizada.',
                'userMessage' => 'No se puede reenviar acceso a un usuario externo desactivado.',
            ], 403);
        }

        app(MagicLinkService::class)->sendMagicLinkToUser($externalUser);

        return response()->json(['message' => 'Se ha reenviado el acceso al usuario externo.']);
    }

    public function activate(ExternalUser $externalUser)
    {
        $this->authorize('update', $externalUser);
        $externalUser->update(['is_active' => true]);

        return response()->json([
            'message' => 'Usuario externo activado correctamente.',
            'data' => new ExternalUserResource($externalUser->fresh()->loadCount('stores')),
        ]);
    }

    public function deactivate(ExternalUser $externalUser)
    {
        $this->authorize('update', $externalUser);
        $externalUser->update(['is_active' => false]);
        $this->actors->revokeTokens($externalUser);

        return response()->json([
            'message' => 'Usuario externo desactivado correctamente.',
            'data' => new ExternalUserResource($externalUser->fresh()->loadCount('stores')),
        ]);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewAny', ExternalUser::class);

        $externalUsers = ExternalUser::query()
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json($externalUsers);
    }
}
