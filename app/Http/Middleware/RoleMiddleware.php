<?php



namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        // Verificar si el usuario tiene uno de los roles requeridos
        if (!$user || !$user->hasAnyRole($roles)) {
            return response()->json([
                'message' => 'Acción no autorizada.',
                'userMessage' => 'No tienes permiso para acceder a esta ruta.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
