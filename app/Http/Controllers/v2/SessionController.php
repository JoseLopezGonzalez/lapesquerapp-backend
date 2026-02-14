<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexSessionRequest;
use App\Http\Resources\v2\SessionResource;
use App\Sanctum\PersonalAccessToken;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Listar todas las sesiones abiertas (del tenant actual).
     */
    public function index(IndexSessionRequest $request)
    {
        $this->authorize('viewAny', PersonalAccessToken::class);

        $query = PersonalAccessToken::with('tokenable')
            ->orderBy('last_used_at', 'desc');

        if ($request->filled('user_id')) {
            $query->where('tokenable_id', $request->input('user_id'));
        }

        $perPage = $request->input('per_page', 10);
        $sessions = $query->paginate($perPage);

        return SessionResource::collection($sessions);
    }

    /**
     * Cerrar una sesión específica.
     */
    public function destroy($id)
    {
        $token = PersonalAccessToken::find($id);

        if (!$token) {
            return response()->json([
                'message' => 'Sesión no encontrada.',
                'userMessage' => 'La sesión especificada no existe o ya fue cerrada.'
            ], 404);
        }

        $this->authorize('delete', $token);

        $token->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}
