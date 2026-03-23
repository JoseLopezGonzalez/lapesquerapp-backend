<?php

namespace App\Http\Controllers\v2;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexRouteTemplateRequest;
use App\Http\Requests\v2\StoreRouteTemplateRequest;
use App\Http\Requests\v2\UpdateRouteTemplateRequest;
use App\Http\Resources\v2\RouteTemplateResource;
use App\Models\RouteTemplate;
use App\Services\v2\RouteTemplateWriteService;
use Illuminate\Database\Eloquent\Builder;

class RouteTemplateController extends Controller
{
    public function index(IndexRouteTemplateRequest $request)
    {
        $query = RouteTemplate::query()->with(['salesperson', 'fieldOperator', 'stops']);
        $this->scopeQueryForUser($query, $request->user());

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->input('name').'%');
        }

        if ($request->filled('fieldOperatorId')) {
            $query->where('field_operator_id', $request->integer('fieldOperatorId'));
        }

        if ($request->filled('salespersonId')) {
            $query->where('salesperson_id', $request->integer('salespersonId'));
        }

        return RouteTemplateResource::collection($query->orderBy('name')->paginate($request->input('perPage', 10)));
    }

    public function store(StoreRouteTemplateRequest $request)
    {
        $template = RouteTemplateWriteService::store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Plantilla de ruta creada correctamente.',
            'data' => new RouteTemplateResource($template),
        ], 201);
    }

    public function show(RouteTemplate $routeTemplate)
    {
        $this->authorize('view', $routeTemplate);

        return response()->json([
            'data' => new RouteTemplateResource($routeTemplate->load(['salesperson', 'fieldOperator', 'stops'])),
        ]);
    }

    public function update(UpdateRouteTemplateRequest $request, RouteTemplate $routeTemplate)
    {
        $template = RouteTemplateWriteService::update($routeTemplate, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Plantilla de ruta actualizada correctamente.',
            'data' => new RouteTemplateResource($template),
        ]);
    }

    public function destroy(RouteTemplate $routeTemplate)
    {
        $this->authorize('delete', $routeTemplate);
        $routeTemplate->delete();

        return response()->json(['message' => 'Plantilla de ruta eliminada correctamente.']);
    }

    private function scopeQueryForUser(Builder $query, $user): void
    {
        if (! $user->hasRole(Role::Comercial->value) || ! $user->salesperson) {
            return;
        }

        $query->where(function (Builder $scoped) use ($user) {
            $scoped->where('salesperson_id', $user->salesperson->id)
                ->orWhere(function (Builder $createdByUser) use ($user) {
                    $createdByUser->whereNull('salesperson_id')
                        ->where('created_by_user_id', $user->id);
                });
        });
    }
}
