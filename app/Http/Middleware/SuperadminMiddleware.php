<?php

namespace App\Http\Middleware;

use App\Sanctum\SuperadminPersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

class SuperadminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $previousModel = Sanctum::$personalAccessTokenModel;

        Sanctum::usePersonalAccessTokenModel(SuperadminPersonalAccessToken::class);

        $user = auth('sanctum')->user();

        Sanctum::usePersonalAccessTokenModel($previousModel);

        if (!$user || !($user instanceof \App\Models\SuperadminUser)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
