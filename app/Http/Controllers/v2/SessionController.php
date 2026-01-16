<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\SessionResource;
use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class SessionController extends Controller
{
    /**
     * Listar todas las sesiones abiertas.
     */
    public function index(Request $request)
    {
        // Filtrar y paginar sesiones
        $query = SanctumPersonalAccessToken::with('tokenable') // Relación con el modelo User
            ->orderBy('last_used_at', 'desc'); // Ordenar por último uso
    
        // Filtros opcionales
        if ($request->has('user_id')) {
            $query->where('tokenable_id', $request->input('user_id'));
        }
    
        // Paginación
        $perPage = $request->input('per_page', 10);
        $sessions = $query->paginate($perPage);
    
        // Usar el recurso para personalizar la salida
        return SessionResource::collection($sessions);
    }
    

    /**
     * Cerrar una sesión específica.
     */
    public function destroy($id)
    {
        $token = SanctumPersonalAccessToken::find($id);

        if (!$token) {
            return response()->json(['message' => 'Sesión no encontrada'], 404);
        }

        $token->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}
