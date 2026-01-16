<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\PunchEventResource;
use App\Models\Employee;
use App\Models\PunchEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PunchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PunchEvent::query();

        // Cargar relación con empleado
        $query->with('employee');

        // Filtro por ID
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por IDs
        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        // Filtro por empleado
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filtro por múltiples empleados
        if ($request->has('employee_ids')) {
            $query->whereIn('employee_id', $request->employee_ids);
        }

        // Filtro por tipo de evento
        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Filtro por dispositivo
        if ($request->has('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        // Filtro por rango de fechas (date_start y date_end)
        // Carbon::parse() usa automáticamente la zona horaria configurada en config/app.php
        if ($request->has('date_start')) {
            $dateStart = Carbon::parse($request->date_start)->startOfDay();
            $query->where('timestamp', '>=', $dateStart);
        }

        if ($request->has('date_end')) {
            $dateEnd = Carbon::parse($request->date_end)->endOfDay();
            $query->where('timestamp', '<=', $dateEnd);
        }

        // Filtro por rango de timestamps (más preciso)
        if ($request->has('timestamp_start')) {
            $timestampStart = Carbon::parse($request->timestamp_start);
            $query->where('timestamp', '>=', $timestampStart);
        }

        if ($request->has('timestamp_end')) {
            $timestampEnd = Carbon::parse($request->timestamp_end);
            $query->where('timestamp', '<=', $timestampEnd);
        }

        // Filtro por día específico (shortcut)
        if ($request->has('date')) {
            $date = Carbon::parse($request->date);
            $query->whereDate('timestamp', $date->toDateString());
        }

        // Ordenar por timestamp descendente (más recientes primero)
        $query->orderBy('timestamp', 'desc');

        $perPage = $request->input('perPage', 15);
        
        return PunchEventResource::collection($query->paginate($perPage));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $punchEvent = PunchEvent::with('employee')->findOrFail($id);

        return response()->json([
            'message' => 'Evento de fichaje obtenido correctamente.',
            'data' => new PunchEventResource($punchEvent),
        ]);
    }

    /**
     * Registrar un nuevo evento de fichaje.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'uid' => 'nullable|string|required_without:employee_id',
            'employee_id' => 'nullable|integer|exists:tenant.employees,id|required_without:uid',
            'device_id' => 'required|string',
        ], [
            'uid.required_without' => 'Debe proporcionar uid o employee_id.',
            'employee_id.required_without' => 'Debe proporcionar uid o employee_id.',
            'employee_id.exists' => 'El empleado especificado no existe.',
        ]);

        // Buscar empleado: prioridad a employee_id si está presente
        if (isset($validated['employee_id'])) {
            $employee = Employee::find($validated['employee_id']);
        } else {
            // Buscar por UID NFC
            $employee = Employee::where('nfc_uid', $validated['uid'])->first();
        }

        if (!$employee) {
            return response()->json([
                'message' => 'Empleado no encontrado.',
                'error' => 'EMPLOYEE_NOT_FOUND',
            ], 404);
        }

        // Usar siempre la hora actual del servidor
        $timestamp = now();

        // Determinar el tipo de evento basándose en el último evento
        $eventType = $this->determineEventType($employee, $timestamp);

        // Crear el evento en una transacción
        try {
            DB::beginTransaction();

            $punchEvent = PunchEvent::create([
                'employee_id' => $employee->id,
                'event_type' => $eventType,
                'device_id' => $validated['device_id'],
                'timestamp' => $timestamp,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Fichaje registrado correctamente.',
                'data' => [
                    'employee_name' => $employee->name,
                    'event_type' => $eventType,
                    'timestamp' => $punchEvent->timestamp->format('Y-m-d H:i:s'),
                    'device_id' => $punchEvent->device_id,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al registrar el fichaje.',
                'error' => 'PUNCH_REGISTRATION_FAILED',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $punchEvent = PunchEvent::with('employee')->findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'sometimes|required|integer|exists:tenant.employees,id',
            'event_type' => 'sometimes|required|in:IN,OUT',
            'device_id' => 'sometimes|required|string',
            'timestamp' => 'sometimes|required|date',
        ], [
            'employee_id.exists' => 'El empleado especificado no existe.',
            'event_type.in' => 'El tipo de evento debe ser IN o OUT.',
            'timestamp.date' => 'El timestamp debe ser una fecha válida.',
        ]);

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
     * Determinar el tipo de evento (IN o OUT) basándose en el último evento.
     *
     * @param \App\Models\Employee $employee
     * @param \Carbon\Carbon $timestamp
     * @return string
     */
    private function determineEventType(Employee $employee, $timestamp): string
    {
        // Obtener el último evento del empleado ordenado por timestamp descendente
        $lastEvent = PunchEvent::where('employee_id', $employee->id)
            ->orderBy('timestamp', 'desc')
            ->first();

        // Si no hay evento previo o el último fue OUT, este es IN
        if (!$lastEvent || $lastEvent->event_type === PunchEvent::TYPE_OUT) {
            return PunchEvent::TYPE_IN;
        }

        // Si el último fue IN, este es OUT
        return PunchEvent::TYPE_OUT;
    }

    /**
     * Obtener datos del dashboard de trabajadores activos.
     */
    public function dashboard(Request $request)
    {
        // Filtro opcional por fecha (por defecto hoy)
        $date = $request->has('date') 
            ? Carbon::parse($request->date)->startOfDay()
            : now()->startOfDay();
        
        $dateEnd = $date->copy()->endOfDay();

        // Obtener todos los empleados
        $allEmployees = Employee::all();

        // Obtener todos los eventos del día
        $todayEvents = PunchEvent::whereBetween('timestamp', [$date, $dateEnd])
            ->with('employee')
            ->orderBy('timestamp', 'desc')
            ->get();

        // Obtener último evento de cada empleado en una sola consulta optimizada
        // Usar subconsulta para obtener el último timestamp de cada empleado
        $employeeIds = $allEmployees->pluck('id');
        
        if ($employeeIds->isEmpty()) {
            $lastEventsByEmployee = collect();
        } else {
            // Usar conexión tenant explícitamente
            $lastEventsByEmployee = DB::connection('tenant')
                ->table('punch_events as pe1')
                ->select('pe1.employee_id', 'pe1.event_type', 'pe1.device_id', 'pe1.timestamp')
                ->whereIn('pe1.employee_id', $employeeIds)
                ->whereRaw('pe1.timestamp = (
                    SELECT MAX(pe2.timestamp)
                    FROM punch_events pe2
                    WHERE pe2.employee_id = pe1.employee_id
                )')
                ->get()
                ->map(function ($item) {
                    return (object) [
                        'employee_id' => $item->employee_id,
                        'event_type' => $item->event_type,
                        'device_id' => $item->device_id,
                        'timestamp' => Carbon::parse($item->timestamp),
                    ];
                })
                ->keyBy('employee_id');
        }

        // Trabajadores activos (último evento es IN)
        $workingEmployees = [];
        $totalEntriesToday = 0;
        $totalExitsToday = 0;
        $entriesByDevice = [];

        foreach ($allEmployees as $employee) {
            // Obtener último evento del empleado desde la colección optimizada
            $lastEventData = $lastEventsByEmployee->get($employee->id);

            // Si el último evento es IN, está trabajando
            $isWorking = $lastEventData && $lastEventData->event_type === PunchEvent::TYPE_IN;

            // Eventos del día del empleado
            $employeeTodayEvents = $todayEvents->where('employee_id', $employee->id);
            $todayEntries = $employeeTodayEvents->where('event_type', PunchEvent::TYPE_IN);
            $todayExits = $employeeTodayEvents->where('event_type', PunchEvent::TYPE_OUT);

            $todayEntriesCount = $todayEntries->count();
            $todayExitsCount = $todayExits->count();
            $totalEntriesToday += $todayEntriesCount;
            $totalExitsToday += $todayExitsCount;

            // Contar entradas por dispositivo
            foreach ($todayEntries as $entry) {
                $deviceId = $entry->device_id;
                $entriesByDevice[$deviceId] = ($entriesByDevice[$deviceId] ?? 0) + 1;
            }

            if ($isWorking) {
                $entryTimestamp = $lastEventData->timestamp;
                $workingMinutes = now()->diffInMinutes($entryTimestamp);
                $workingHours = round($workingMinutes / 60, 2);
                
                // Calcular tiempo formateado
                $hours = floor($workingMinutes / 60);
                $minutes = $workingMinutes % 60;
                $workingTimeFormatted = $hours > 0 
                    ? "{$hours}h {$minutes}m" 
                    : "{$minutes}m";

                // Calcular total de horas trabajadas hoy (suma de sesiones completas)
                $todayTotalHours = 0;
                $todayTotalMinutes = 0;
                
                // Agrupar eventos en pares IN-OUT
                $events = $employeeTodayEvents->sortBy('timestamp')->values();
                for ($i = 0; $i < $events->count(); $i++) {
                    if ($events[$i]->event_type === PunchEvent::TYPE_IN) {
                        // Buscar el siguiente OUT
                        $nextOut = $events->slice($i + 1)->firstWhere('event_type', PunchEvent::TYPE_OUT);
                        
                        if ($nextOut) {
                            $sessionMinutes = $events[$i]->timestamp->diffInMinutes($nextOut->timestamp);
                            $todayTotalMinutes += $sessionMinutes;
                        } else {
                            // Si no hay OUT, es la sesión actual
                            $sessionMinutes = $events[$i]->timestamp->diffInMinutes(now());
                            $todayTotalMinutes += $sessionMinutes;
                        }
                    }
                }
                $todayTotalHours = round($todayTotalMinutes / 60, 2);

                $workingEmployees[] = [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'nfcUid' => $employee->nfc_uid,
                    'isWorking' => true,
                    'entryTimestamp' => $entryTimestamp->format('Y-m-d H:i:s'),
                    'entryDeviceId' => $lastEventData->device_id,
                    'workingHours' => $workingHours,
                    'workingTimeFormatted' => $workingTimeFormatted,
                    'workingMinutes' => $workingMinutes,
                    'todayEntriesCount' => $todayEntriesCount,
                    'todayExitsCount' => $todayExitsCount,
                    'todayTotalHours' => $todayTotalHours,
                ];
            }
        }

        // Ordenar trabajadores activos por tiempo trabajado (más tiempo primero)
        usort($workingEmployees, function ($a, $b) {
            return $b['workingMinutes'] <=> $a['workingMinutes'];
        });

        // Últimos fichajes recientes (últimos 30 minutos)
        $recentTimeLimit = now()->subMinutes(30);
        $recentPunches = $todayEvents
            ->where('timestamp', '>=', $recentTimeLimit)
            ->take(20)
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'employeeName' => $event->employee->name,
                    'eventType' => $event->event_type,
                    'timestamp' => $event->timestamp->format('Y-m-d H:i:s'),
                    'deviceId' => $event->device_id,
                ];
            })
            ->values();

        // Estadísticas agregadas
        $statistics = [
            'totalWorking' => count($workingEmployees),
            'totalEmployees' => $allEmployees->count(),
            'totalEntriesToday' => $totalEntriesToday,
            'totalExitsToday' => $totalExitsToday,
            'entriesByDevice' => $entriesByDevice,
        ];

        return response()->json([
            'message' => 'Datos del dashboard obtenidos correctamente.',
            'data' => [
                'workingEmployees' => $workingEmployees,
                'statistics' => $statistics,
                'recentPunches' => $recentPunches,
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     * 
     * Nota: Normalmente los eventos históricos no se deberían eliminar,
     * pero se permite para casos especiales (correcciones, etc.)
     */
    public function destroy(string $id)
    {
        $punchEvent = PunchEvent::findOrFail($id);
        $punchEvent->delete();

        return response()->json([
            'message' => 'Evento de fichaje eliminado correctamente.',
        ]);
    }

    /**
     * Remove multiple resources from storage.
     */
    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:tenant.punch_events,id',
        ], [
            'ids.required' => 'Debe proporcionar un array de IDs.',
            'ids.array' => 'Los IDs deben ser un array.',
            'ids.*.integer' => 'Cada ID debe ser un número entero.',
            'ids.*.exists' => 'Uno o más IDs no existen.',
        ]);

        PunchEvent::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'message' => 'Eventos de fichaje eliminados correctamente.',
        ]);
    }
}

