<?php

namespace App\Http\Controllers\v2\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\Superadmin\StoreSuperadminUserRequest;
use App\Http\Requests\v2\Superadmin\UpdateSuperadminUserRequest;
use App\Http\Resources\v2\Superadmin\SuperadminUserResource;
use App\Models\SuperadminUser;
use App\Services\SuperadminAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SuperadminUserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $admins = SuperadminUser::orderBy('name')
            ->paginate((int) $request->query('per_page', 20));

        return SuperadminUserResource::collection($admins);
    }

    public function show(SuperadminUser $admin): SuperadminUserResource
    {
        return new SuperadminUserResource($admin);
    }

    public function store(StoreSuperadminUserRequest $request): JsonResponse
    {
        $admin = SuperadminUser::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
        ]);

        if ($request->boolean('send_access_email', true)) {
            try {
                app(SuperadminAuthService::class)->sendAccessEmail($admin);
            } catch (\Throwable $e) {
                report($e);
                return (new SuperadminUserResource($admin))
                    ->additional(['warning' => 'Usuario creado pero no se pudo enviar el correo de acceso.'])
                    ->response()
                    ->setStatusCode(201);
            }
        }

        return (new SuperadminUserResource($admin))
            ->additional(['message' => 'Usuario superadmin creado correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSuperadminUserRequest $request, SuperadminUser $admin): SuperadminUserResource
    {
        $admin->update($request->validated());

        return new SuperadminUserResource($admin->fresh());
    }

    public function destroy(Request $request, SuperadminUser $admin): JsonResponse
    {
        if ($request->user()->id === $admin->id) {
            return response()->json([
                'message' => 'No puedes eliminar tu propia cuenta de superadmin.',
            ], 422);
        }

        $admin->tokens()->delete();
        $admin->delete();

        return response()->json(['message' => 'Usuario superadmin eliminado correctamente.']);
    }
}
