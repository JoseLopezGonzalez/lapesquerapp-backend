<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\BulkStorePunchesRequest;
use App\Http\Requests\v2\BulkValidatePunchesRequest;
use App\Http\Requests\v2\DestroyMultiplePunchesRequest;
use App\Http\Requests\v2\StoreManualPunchRequest;
use App\Http\Requests\v2\UpdatePunchEventRequest;
use App\Http\Resources\v2\PunchEventResource;
use App\Models\Employee;
use App\Models\PunchEvent;
use App\Sanctum\PersonalAccessToken;
use App\Services\PunchCalendarService;
use App\Services\PunchDashboardService;
use App\Services\PunchEventListService;
use App\Services\PunchStatisticsService;
use App\Services\PunchEventWriteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PunchController extends Controller
{
    public function __construct(
        private PunchDashboardService $dashboardService,
        private PunchCalendarService $calendarService,
        private PunchStatisticsService $statisticsService,
        private PunchEventListService $listService,
        private PunchEventWriteService $writeService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PunchEvent::class);

        $query = PunchEvent::query()->with('employee');
        $query = $this->listService->applyFiltersToQuery($query, $request->all());
        $query->orderBy('timestamp', 'desc');

        return PunchEventResource::collection($query->paginate($request->input('perPage', 15)));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $punchEvent = PunchEvent::with('employee')->findOrFail($id);
        $this->authorize('view', $punchEvent);

        return response()->json([
            'message' => 'Evento de fichaje obtenido correctamente.',
            'data' => new PunchEventResource($punchEvent),
        ]);
    }

    /**
     * Registrar un nuevo evento de fichaje.
     * Detecta automáticamente si es un fichaje manual (con timestamp y event_type) o NFC.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Detectar si es un fichaje manual (tiene timestamp y event_type)
        if ($request->has('timestamp') && $request->has('event_type')) {
            // Es un fichaje manual, requiere autenticación
            // Intentar autenticar manualmente si hay token en el header
            $this->authenticateManualRequest($request);
            return $this->storeManual($request);
        }

        // Es un fichaje NFC (dispositivo físico)
        $validated = $request->validate([
            'uid' => 'nullable|string|required_without:employee_id',
            'employee_id' => 'nullable|integer|exists:tenant.employees,id|required_without:uid',
            'device_id' => 'required|string',
        ], [
            'uid.required_without' => 'Debe proporcionar uid o employee_id.',
            'employee_id.required_without' => 'Debe proporcionar uid o employee_id.',
            'employee_id.exists' => 'El empleado especificado no existe.',
        ]);

        $result = $this->writeService->storeFromNfc($validated);

        if (!($result['success'] ?? false)) {
            if (($result['error'] ?? '') === 'EMPLOYEE_NOT_FOUND') {
                return response()->json([
                    'message' => 'Empleado no encontrado.',
                    'error' => 'EMPLOYEE_NOT_FOUND',
                ], 404);
            }
            return response()->json([
                'message' => 'Error al registrar el fichaje.',
                'error' => 'PUNCH_REGISTRATION_FAILED',
            ], 500);
        }

        $punchEvent = $result['punchEvent'];
        $employee = $result['employee'];

        return response()->json([
            'message' => 'Fichaje registrado correctamente.',
            'data' => [
                'employee_name' => $employee->name,
                'event_type' => $result['event_type'],
                'timestamp' => $punchEvent->timestamp->format('Y-m-d H:i:s'),
                'device_id' => $punchEvent->device_id,
            ],
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePunchEventRequest $request, string $id)
    {
        $punchEvent = PunchEvent::with('employee')->findOrFail($id);
        $this->authorize('update', $punchEvent);

        $validated = $request->validated();

        try {
            DB::beginTransaction();

            // Actualizar solo los campos proporcionados
            if (isset($validated['employee_id'])) {
                $punchEvent->employee_id = $validated['employee_id'];
            }

            if (isset($validated['event_type'])) {
                $punchEvent->event_type = $validated['event_type'];
            }

            if (isset($validated['device_id'])) {
                $punchEvent->device_id = $validated['device_id'];
            }

            if (isset($validated['timestamp'])) {
                $punchEvent->timestamp = Carbon::parse($validated['timestamp']);
            }

            $punchEvent->save();

            // Recargar relación con empleado
            $punchEvent->load('employee');

            DB::commit();

            return response()->json([
                'message' => 'Evento de fichaje actualizado correctamente.',
                'data' => new PunchEventResource($punchEvent),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al actualizar el evento de fichaje.',
                'error' => 'PUNCH_UPDATE_FAILED',
            ], 500);
        }
    }

    /**
     * Obtener datos del dashboard de trabajadores activos.
     */
    public function dashboard(Request $request)
    {
        $this->authorize('viewAny', PunchEvent::class);

        $date = $request->has('date')
            ? Carbon::parse($request->date)->startOfDay()
            : null;

        $result = $this->dashboardService->getData($date);

        return response()->json($result);
    }

    /**
     * Obtener fichajes agrupados por día para un calendario.
     */
    public function calendar(Request $request)
    {
        $this->authorize('viewAny', PunchEvent::class);

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        if ($year < 2000 || $year > 2100) {
            return response()->json([
                'message' => 'El año debe ser un número válido entre 2000 y 2100.',
                'userMessage' => 'El año proporcionado no es válido.',
                'error' => 'INVALID_YEAR',
            ], 400);
        }

        if ($month < 1 || $month > 12) {
            return response()->json([
                'message' => 'El mes debe ser un número válido entre 1 y 12.',
                'userMessage' => 'El mes proporcionado no es válido.',
                'error' => 'INVALID_MONTH',
            ], 400);
        }

        $data = $this->calendarService->getData($year, $month);

        return response()->json(['data' => $data]);
    }

    /**
     * Obtener estadísticas de trabajadores por período.
     */
    public function statistics(Request $request)
    {
        $this->authorize('viewAny', PunchEvent::class);

        if (!$request->has('date_start') || !$request->has('date_end')) {
            return response()->json([
                'message' => 'Los parámetros date_start y date_end son requeridos.',
                'userMessage' => 'Debe proporcionar las fechas de inicio y fin del período.',
                'error' => 'MISSING_DATE_PARAMETERS',
            ], 400);
        }

        try {
            $dateStart = Carbon::parse($request->date_start)->startOfDay();
            $dateEnd = Carbon::parse($request->date_end)->endOfDay();
            if ($dateStart->gt($dateEnd)) {
                return response()->json([
                    'message' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.',
                    'userMessage' => 'La fecha de inicio no puede ser posterior a la fecha de fin.',
                    'error' => 'INVALID_DATE_RANGE',
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Formato de fecha inválido. Use formato YYYY-MM-DD.',
                'userMessage' => 'El formato de fecha es incorrecto. Use el formato YYYY-MM-DD (ejemplo: 2026-01-15).',
                'error' => 'INVALID_DATE_FORMAT',
            ], 400);
        }

        $allEmployees = Employee::all();
        if ($allEmployees->isEmpty()) {
            return response()->json([
                'message' => 'No hay empleados registrados.',
                'userMessage' => 'No hay empleados registrados en el sistema.',
                'data' => $this->statisticsService->getEmptyResponseForDates($dateStart, $dateEnd),
            ]);
        }

        $data = $this->statisticsService->getData($dateStart, $dateEnd);

        return response()->json([
            'message' => 'Estadísticas obtenidas correctamente.',
            'data' => $data,
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $punchEvent = PunchEvent::findOrFail($id);
        $this->authorize('delete', $punchEvent);
        $punchEvent->delete();

        return response()->json([
            'message' => 'Evento de fichaje eliminado correctamente.',
        ]);
    }

    /**
     * Remove multiple resources from storage.
     */
    public function destroyMultiple(DestroyMultiplePunchesRequest $request)
    {
        $this->authorize('viewAny', PunchEvent::class);

        $validated = $request->validated();
        $punchEvents = PunchEvent::whereIn('id', $validated['ids'])->get();
        foreach ($punchEvents as $punchEvent) {
            $this->authorize('delete', $punchEvent);
        }

        PunchEvent::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'message' => 'Eventos de fichaje eliminados correctamente.',
        ]);
    }

    /**
     * Crear un fichaje manual individual.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Autenticar manualmente una request si tiene token Bearer.
     * Esto es necesario porque la ruta es pública pero los fichajes manuales requieren auth.
     *
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    private function authenticateManualRequest(Request $request)
    {
        $token = $request->bearerToken();
        
        if ($token) {
            // Buscar el token en la base de datos
            $accessToken = PersonalAccessToken::findToken($token);
            
            if ($accessToken) {
                // Autenticar al usuario asociado al token
                $user = $accessToken->tokenable;
                if ($user) {
                    Auth::guard('sanctum')->setUser($user);
                    // También establecer el usuario en la request para que $request->user() funcione
                    $request->setUserResolver(function () use ($user) {
                        return $user;
                    });
                }
            }
        }
    }

    public function storeManual(Request $request)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Error al crear fichaje',
                'userMessage' => 'Los fichajes manuales requieren autenticación.',
                'errors' => ['auth' => ['Se requiere autenticación para crear fichajes manuales.']],
            ], 401);
        }

        $this->authorize('create', PunchEvent::class);

        $validated = $request->validate(
            StoreManualPunchRequest::getRules(),
            StoreManualPunchRequest::getMessages()
        );

        $result = $this->writeService->storeManual($validated);

        if (!($result['success'] ?? false)) {
            $status = isset($result['errors']) ? 422 : 500;
            return response()->json([
                'message' => 'Error al crear fichaje',
                'userMessage' => $result['userMessage'] ?? 'Error al registrar el fichaje.',
                'errors' => $result['errors'] ?? [],
            ], $status);
        }

        $punchEvent = $result['punchEvent'];

        return response()->json([
            'data' => [
                'id' => $punchEvent->id,
                'employee_id' => $punchEvent->employee_id,
                'event_type' => $punchEvent->event_type,
                'timestamp' => $punchEvent->timestamp->toIso8601String(),
                'device_id' => $punchEvent->device_id,
                'created_at' => $punchEvent->created_at->toIso8601String(),
                'updated_at' => $punchEvent->updated_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Validar múltiples fichajes antes de crearlos.
     */
    public function bulkValidate(BulkValidatePunchesRequest $request)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Error al validar fichajes',
                'userMessage' => 'La validación de fichajes manuales requiere autenticación.',
                'errors' => ['auth' => ['Se requiere autenticación para validar fichajes manuales.']],
            ], 401);
        }

        $this->authorize('create', PunchEvent::class);

        $data = $this->writeService->bulkValidate($request->validated());

        return response()->json(['data' => $data]);
    }

    /**
     * Crear múltiples fichajes de forma masiva.
     */
    public function bulkStore(BulkStorePunchesRequest $request)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Error al crear fichajes masivos',
                'userMessage' => 'La creación de fichajes manuales requiere autenticación.',
                'errors' => ['auth' => ['Se requiere autenticación para crear fichajes manuales.']],
            ], 401);
        }

        $this->authorize('create', PunchEvent::class);

        $result = $this->writeService->bulkStore($request->validated());

        if (($result['allInvalid'] ?? false)) {
            return response()->json([
                'message' => 'Error al crear fichajes masivos',
                'userMessage' => 'Todos los fichajes tienen errores de validación.',
                'errors' => array_map(fn ($i) => ['index' => $i['index'], 'message' => implode(' ', $i['errors'])], $result['invalidPunches'] ?? []),
            ], 422);
        }

        if (($result['exception'] ?? false)) {
            return response()->json([
                'message' => 'Error al crear fichajes masivos',
                'userMessage' => $result['userMessage'] ?? 'Ocurrió un error inesperado al registrar los fichajes.',
                'errors' => [],
            ], 500);
        }

        if (($result['rollback'] ?? false)) {
            return response()->json([
                'message' => 'Error al crear fichajes masivos',
                'userMessage' => 'Algunos fichajes no pudieron crearse. Ningún fichaje fue registrado.',
                'data' => [
                    'created' => $result['created'] ?? 0,
                    'failed' => $result['failed'] ?? 0,
                    'results' => $result['results'] ?? [],
                    'errors' => $result['errors'] ?? [],
                ],
            ], 200);
        }

        return response()->json([
            'data' => [
                'created' => $result['created'],
                'failed' => $result['failed'],
                'results' => $result['results'],
                'errors' => $result['errors'],
            ],
        ], 201);
    }
}

