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

        // Obtener todos los eventos del día ordenados por timestamp ascendente (para cálculos)
        $todayEvents = PunchEvent::whereBetween('timestamp', [$date, $dateEnd])
            ->with('employee')
            ->orderBy('timestamp', 'asc')
            ->get()
            ->groupBy('employee_id');

        // Obtener último evento de cada empleado en una sola consulta optimizada
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

        // Procesar todos los empleados
        $allEmployeesData = [];
        $workingEmployees = [];
        $totalEntriesToday = 0;
        $totalExitsToday = 0;
        $entriesByDevice = [];

        foreach ($allEmployees as $employee) {
            // Obtener último evento del empleado
            $lastEventData = $lastEventsByEmployee->get($employee->id);
            
            // Eventos del día del empleado (ordenados por timestamp)
            $employeeTodayEvents = $todayEvents->get($employee->id, collect())->sortBy('timestamp')->values();
            
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

            // Determinar estado
            $status = 'no_ha_fichado';
            if ($lastEventData) {
                $lastEventIsToday = $lastEventData->timestamp->isSameDay($date);
                
                if ($lastEventData->event_type === PunchEvent::TYPE_IN) {
                    // Si el último evento es IN y es de hoy, está trabajando
                    $status = $lastEventIsToday ? 'trabajando' : 'ha_finalizado';
                } else {
                    // Si el último evento es OUT
                    if ($lastEventIsToday) {
                        // Calcular horas transcurridas desde la última salida
                        $hoursSinceLastExit = now()->diffInHours($lastEventData->timestamp);
                        
                        // Si han pasado menos de 2 horas, está descansando
                        // Si han pasado 2 horas o más, ha finalizado su jornada
                        $status = $hoursSinceLastExit < 2 ? 'descansando' : 'ha_finalizado';
                    } else {
                        // El último evento OUT no es de hoy, ya finalizó
                        $status = 'ha_finalizado';
                    }
                }
            }

            $isWorking = $status === 'trabajando';

            // Primera entrada del día
            $firstEntry = $todayEntries->first();
            $firstEntryTimestamp = $firstEntry ? $firstEntry->timestamp : null;

            // Última salida del día
            $lastExit = $todayExits->last();
            $lastExitTimestamp = $lastExit ? $lastExit->timestamp : null;

            // Calcular tiempo total trabajado hoy (suma de todas las sesiones)
            $todayTotalMinutes = 0;
            $completeSessionsCount = 0;
            $breakTimeMinutes = 0;

            $events = $employeeTodayEvents;
            for ($i = 0; $i < $events->count(); $i++) {
                if ($events[$i]->event_type === PunchEvent::TYPE_IN) {
                    $nextOut = $events->slice($i + 1)->firstWhere('event_type', PunchEvent::TYPE_OUT);
                    
                    if ($nextOut) {
                        // Sesión completa
                        $sessionMinutes = $events[$i]->timestamp->diffInMinutes($nextOut->timestamp);
                        $todayTotalMinutes += $sessionMinutes;
                        $completeSessionsCount++;
                    } else {
                        // Sesión actual (sin salida todavía)
                        $sessionMinutes = $events[$i]->timestamp->diffInMinutes(now());
                        $todayTotalMinutes += $sessionMinutes;
                    }
                } elseif ($events[$i]->event_type === PunchEvent::TYPE_OUT && $i > 0) {
                    // Calcular tiempo de descanso: tiempo desde salida hasta siguiente entrada
                    $nextIn = $events->slice($i + 1)->firstWhere('event_type', PunchEvent::TYPE_IN);
                    if ($nextIn) {
                        $breakMinutes = $events[$i]->timestamp->diffInMinutes($nextIn->timestamp);
                        $breakTimeMinutes += $breakMinutes;
                    }
                }
            }

            $todayTotalHours = round($todayTotalMinutes / 60, 2);
            $breakTimeHours = round($breakTimeMinutes / 60, 2);

            // Formatear tiempo total trabajado hoy
            $todayTotalHoursFormatted = $this->formatTime($todayTotalMinutes);

            // Tiempo desde última acción (si existe)
            $minutesSinceLastAction = $lastEventData 
                ? now()->diffInMinutes($lastEventData->timestamp) 
                : null;

            // Tiempo trabajando en sesión actual (solo si está trabajando)
            $currentSessionMinutes = null;
            $currentSessionFormatted = null;
            $currentEntryTimestamp = null;
            $currentEntryDeviceId = null;

            if ($isWorking && $lastEventData) {
                $currentSessionMinutes = now()->diffInMinutes($lastEventData->timestamp);
                $currentSessionFormatted = $this->formatTime($currentSessionMinutes);
                $currentEntryTimestamp = $lastEventData->timestamp->format('Y-m-d H:i:s');
                $currentEntryDeviceId = $lastEventData->device_id;
            }

            // Construir datos del empleado
            $employeeData = [
                'id' => $employee->id,
                'name' => $employee->name,
                'nfcUid' => $employee->nfc_uid,
                'status' => $status,
                'isWorking' => $isWorking,
                // Primera entrada del día
                'firstEntryTimestamp' => $firstEntryTimestamp ? $firstEntryTimestamp->format('Y-m-d H:i:s') : null,
                // Última salida del día
                'lastExitTimestamp' => $lastExitTimestamp ? $lastExitTimestamp->format('Y-m-d H:i:s') : null,
                // Tiempo total trabajado hoy
                'todayTotalHours' => $todayTotalHours,
                'todayTotalMinutes' => $todayTotalMinutes,
                'todayTotalHoursFormatted' => $todayTotalHoursFormatted,
                // Sesiones completas
                'completeSessionsCount' => $completeSessionsCount,
                // Tiempo de descanso/pausas
                'breakTimeMinutes' => $breakTimeMinutes,
                'breakTimeHours' => $breakTimeHours,
                'breakTimeFormatted' => $this->formatTime($breakTimeMinutes),
                // Contadores de entradas/salidas
                'todayEntriesCount' => $todayEntriesCount,
                'todayExitsCount' => $todayExitsCount,
                // Tiempo desde última acción
                'minutesSinceLastAction' => $minutesSinceLastAction,
                'timeSinceLastActionFormatted' => $minutesSinceLastAction ? $this->formatTime($minutesSinceLastAction) : null,
                // Sesión actual (solo si está trabajando)
                'currentSessionMinutes' => $currentSessionMinutes,
                'currentSessionHours' => $currentSessionMinutes ? round($currentSessionMinutes / 60, 2) : null,
                'currentSessionFormatted' => $currentSessionFormatted,
                'currentEntryTimestamp' => $currentEntryTimestamp,
                'currentEntryDeviceId' => $currentEntryDeviceId,
            ];

            $allEmployeesData[] = $employeeData;

            // Si está trabajando, añadir también a la lista de trabajadores activos
            if ($isWorking) {
                $workingEmployees[] = $employeeData;
            }
        }

        // Ordenar trabajadores activos por tiempo trabajado en sesión actual (más tiempo primero)
        usort($workingEmployees, function ($a, $b) {
            return ($b['currentSessionMinutes'] ?? 0) <=> ($a['currentSessionMinutes'] ?? 0);
        });

        // Ordenar todos los empleados por tiempo total trabajado hoy (más tiempo primero)
        usort($allEmployeesData, function ($a, $b) {
            return $b['todayTotalMinutes'] <=> $a['todayTotalMinutes'];
        });

        // Últimos fichajes recientes (últimos 30 minutos)
        $recentTimeLimit = now()->subMinutes(30);
        $recentPunches = PunchEvent::whereBetween('timestamp', [$date, $dateEnd])
            ->where('timestamp', '>=', $recentTimeLimit)
            ->with('employee')
            ->orderBy('timestamp', 'desc')
            ->take(20)
            ->get()
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

        // Detectar faltas de fichaje en días anteriores (sesiones abiertas)
        $missingPunches = [];
        $yesterday = now()->subDay()->startOfDay();
        
        // Buscar todos los eventos IN de días anteriores (excluyendo hoy)
        $pastInEvents = PunchEvent::where('event_type', PunchEvent::TYPE_IN)
            ->where('timestamp', '<', $date)
            ->where('timestamp', '>=', $yesterday->copy()->subDays(30)) // Últimos 30 días
            ->with('employee')
            ->orderBy('timestamp', 'desc')
            ->get()
            ->groupBy('employee_id');

        foreach ($pastInEvents as $employeeId => $inEvents) {
            $employee = $inEvents->first()->employee;
            
            foreach ($inEvents as $inEvent) {
                $eventDate = $inEvent->timestamp->startOfDay();
                $eventDateEnd = $eventDate->copy()->endOfDay();
                
                // Verificar si existe un OUT en el mismo día después de este IN
                $hasOut = PunchEvent::where('employee_id', $employeeId)
                    ->where('event_type', PunchEvent::TYPE_OUT)
                    ->where('timestamp', '>', $inEvent->timestamp)
                    ->whereBetween('timestamp', [$eventDate, $eventDateEnd])
                    ->exists();
                
                // Si no hay OUT, es una sesión abierta (falta de fichaje)
                if (!$hasOut) {
                    // Verificar si no está ya en la lista para este empleado y día
                    $key = $employeeId . '_' . $eventDate->format('Y-m-d');
                    
                    if (!isset($missingPunches[$key])) {
                        $daysAgo = now()->startOfDay()->diffInDays($eventDate);
                        
                        $missingPunches[$key] = [
                            'employeeId' => $employee->id,
                            'employeeName' => $employee->name,
                            'nfcUid' => $employee->nfc_uid,
                            'date' => $eventDate->format('Y-m-d'),
                            'daysAgo' => $daysAgo,
                            'entryTimestamp' => $inEvent->timestamp->format('Y-m-d H:i:s'),
                            'entryDeviceId' => $inEvent->device_id,
                            'hoursOpen' => round(now()->diffInHours($inEvent->timestamp), 1),
                        ];
                    }
                }
            }
        }

        // Ordenar por días atrás (más recientes primero)
        usort($missingPunches, function ($a, $b) {
            return $a['daysAgo'] <=> $b['daysAgo'];
        });

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
                'allEmployees' => $allEmployeesData,
                'workingEmployees' => $workingEmployees,
                'statistics' => $statistics,
                'recentPunches' => $recentPunches,
                'errors' => [
                    'missingPunches' => array_values($missingPunches),
                    'totalMissingPunches' => count($missingPunches),
                ],
            ],
        ]);
    }

    /**
     * Obtener estadísticas de trabajadores por período.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        // Validar que se proporcionen date_start y date_end
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
            
            // Validar que date_start <= date_end
            if ($dateStart->gt($dateEnd)) {
                return response()->json([
                    'message' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.',
                    'userMessage' => 'La fecha de inicio no puede ser posterior a la fecha de fin.',
                    'error' => 'INVALID_DATE_RANGE',
                ], 400);
            }

            // Generar label basado en las fechas
            $daysDiff = $dateStart->diffInDays($dateEnd);
            $period = 'custom';
            
            if ($daysDiff <= 7) {
                // Rango corto: mostrar fechas
                $periodLabel = $dateStart->format('d/m/Y') . ' - ' . $dateEnd->format('d/m/Y');
            } elseif ($dateStart->isSameMonth($dateEnd) && $dateStart->day === 1 && $dateEnd->isLastOfMonth()) {
                // Es un mes completo
                $months = [
                    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
                    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
                    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
                ];
                $monthName = $months[$dateStart->month] ?? $dateStart->format('F');
                $periodLabel = ucfirst($monthName) . ' ' . $dateStart->year;
                $period = 'month';
            } elseif ($dateStart->isSameYear($dateEnd) && $dateStart->dayOfYear === 1 && $dateEnd->isLastOfYear()) {
                // Es un año completo
                $periodLabel = $dateStart->format('Y');
                $period = 'year';
            } else {
                // Rango personalizado
                $periodLabel = $dateStart->format('d/m/Y') . ' - ' . $dateEnd->format('d/m/Y');
            }

            // Calcular período anterior basado en la duración del período actual
            $daysDiff = $dateStart->diffInDays($dateEnd);
            // El período anterior tiene la misma duración, pero justo antes del período actual
            $previousDateEnd = $dateStart->copy()->subDay()->endOfDay();
            $previousDateStart = $previousDateEnd->copy()->subDays($daysDiff)->startOfDay();
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Formato de fecha inválido. Use formato YYYY-MM-DD.',
                'userMessage' => 'El formato de fecha es incorrecto. Use el formato YYYY-MM-DD (ejemplo: 2026-01-15).',
                'error' => 'INVALID_DATE_FORMAT',
            ], 400);
        }

        // Obtener todos los empleados
        $allEmployees = Employee::all();
        $employeeIds = $allEmployees->pluck('id');

        if ($employeeIds->isEmpty()) {
            return response()->json([
                'message' => 'No hay empleados registrados.',
                'userMessage' => 'No hay empleados registrados en el sistema.',
                'data' => $this->getEmptyStatisticsResponse($periodLabel, $dateStart, $dateEnd, $period),
            ]);
        }

        // Obtener todos los eventos del período
        $periodEvents = PunchEvent::whereBetween('timestamp', [$dateStart, $dateEnd])
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('timestamp', 'asc')
            ->get()
            ->groupBy('employee_id');

        // Obtener eventos del período anterior para comparación
        $previousPeriodEvents = PunchEvent::whereBetween('timestamp', [$previousDateStart, $previousDateEnd])
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('timestamp', 'asc')
            ->get()
            ->groupBy('employee_id');

        // Procesar datos por empleado
        // IMPORTANTE: Las incidencias (entradas sin salida) NO contaminan las estadísticas
        $employeeStats = [];
        $totalHours = 0;
        $totalDaysWithActivity = [];
        $totalSessions = 0;
        $closedSessionsCount = 0;
        $totalSessionsCount = 0;
        $employeeHours = [];
        $allDayHours = []; // Para calcular media de horas por jornada (solo jornadas completas)
        
        // Detalles de incidencias y anomalías
        $incidentsDetails = [];
        $anomalousDaysDetails = [];
        $dayActivityDetails = []; // Para desglose de actividad por día
        $employeeActivityDetails = []; // Para desglose de actividad por empleado

        foreach ($allEmployees as $employee) {
            $employeeEvents = $periodEvents->get($employee->id, collect())->sortBy('timestamp')->values();
            
            if ($employeeEvents->isEmpty()) {
                continue;
            }

            // Agrupar eventos por día
            $eventsByDay = $employeeEvents->groupBy(function ($event) {
                return $event->timestamp->format('Y-m-d');
            });

            $employeeTotalMinutes = 0;
            $employeeDaysWithActivity = 0;
            $employeeSessions = 0;
            $employeeClosedSessions = 0;

            foreach ($eventsByDay as $day => $dayEvents) {
                $dayStart = Carbon::parse($day)->startOfDay();
                $dayEnd = Carbon::parse($day)->endOfDay();
                
                // Ordenar eventos del día por timestamp
                $dayEvents = $dayEvents->sortBy('timestamp')->values();
                
                $dayMinutes = 0;
                $daySessions = 0;
                $dayClosedSessions = 0;
                $hasOpenSession = false;
                $openSessionEntry = null;

                // Calcular horas del día (SOLO sesiones completas)
                for ($i = 0; $i < $dayEvents->count(); $i++) {
                    if ($dayEvents[$i]->event_type === PunchEvent::TYPE_IN) {
                        $nextOut = $dayEvents->slice($i + 1)->firstWhere('event_type', PunchEvent::TYPE_OUT);
                        
                        if ($nextOut) {
                            // Sesión completa - SÍ cuenta en estadísticas
                            $sessionMinutes = $dayEvents[$i]->timestamp->diffInMinutes($nextOut->timestamp);
                            $dayMinutes += $sessionMinutes;
                            $daySessions++;
                            $dayClosedSessions++;
                            $closedSessionsCount++;
                        } else {
                            // Sesión abierta (incidencia) - NO cuenta en estadísticas
                            $hasOpenSession = true;
                            $openSessionEntry = $dayEvents[$i];
                            // NO sumamos minutos ni sesiones a las estadísticas
                        }
                        $totalSessionsCount++;
                    }
                }

                // Registrar incidencia si existe
                if ($hasOpenSession && $openSessionEntry) {
                    $incidentsDetails[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'date' => $day,
                        'entry_timestamp' => $openSessionEntry->timestamp->format('Y-m-d H:i:s'),
                        'entry_time' => $openSessionEntry->timestamp->format('H:i:s'),
                        'device_id' => $openSessionEntry->device_id,
                    ];
                }

                // Solo contar días con sesiones completas en estadísticas
                if ($dayMinutes > 0) {
                    $dayHours = round($dayMinutes / 60, 2);
                    $employeeTotalMinutes += $dayMinutes;
                    $employeeDaysWithActivity++;
                    // Marcar día como activo (usar true para contar días únicos)
                    $totalDaysWithActivity[$day] = true;
                    // Acumular sesiones para el desglose
                    if (!isset($totalDaysWithActivity[$day . '_sessions'])) {
                        $totalDaysWithActivity[$day . '_sessions'] = 0;
                    }
                    $totalDaysWithActivity[$day . '_sessions'] += $dayClosedSessions;
                    
                    $employeeSessions += $daySessions;
                    $employeeClosedSessions += $dayClosedSessions;
                    $allDayHours[] = [
                        'hours' => $dayHours,
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'date' => $day,
                    ];

                    // Registrar actividad por día
                    if (!isset($dayActivityDetails[$day])) {
                        $dayActivityDetails[$day] = [
                            'date' => $day,
                            'sessions' => 0,
                            'employees' => 0,
                        ];
                    }
                    $dayActivityDetails[$day]['sessions'] += $dayClosedSessions;
                    $dayActivityDetails[$day]['employees']++;
                }
            }

            // Solo contar empleados con sesiones completas
            if ($employeeTotalMinutes > 0) {
                $employeeHoursValue = round($employeeTotalMinutes / 60, 2);
                $totalHours += $employeeHoursValue;
                $totalSessions += $employeeSessions;
                $employeeHours[] = $employeeHoursValue;

                $employeeStats[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'total_hours' => $employeeHoursValue,
                    'total_minutes' => $employeeTotalMinutes,
                    'days_with_activity' => $employeeDaysWithActivity,
                    'sessions' => $employeeClosedSessions, // Solo sesiones cerradas
                ];

                // Registrar actividad por empleado
                $employeeActivityDetails[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'sessions' => $employeeClosedSessions,
                    'days' => $employeeDaysWithActivity,
                ];
            }
        }

        // Calcular media de horas por jornada y detectar jornadas anómalas (solo jornadas completas)
        $anomalousDaysCount = 0;
        if (!empty($allDayHours)) {
            $dayHoursValues = array_column($allDayHours, 'hours');
            $averageHoursPerDay = array_sum($dayHoursValues) / count($dayHoursValues);
            // Considerar anómalas las jornadas que son menos del 50% o más del 200% de la media
            $minThreshold = $averageHoursPerDay * 0.5;
            $maxThreshold = $averageHoursPerDay * 2.0;
            
            foreach ($allDayHours as $dayData) {
                $dayHours = $dayData['hours'];
                if ($dayHours < $minThreshold || $dayHours > $maxThreshold) {
                    $anomalousDaysCount++;
                    $reason = $dayHours < $minThreshold ? 'muy_pocas_horas' : 'muchas_horas';
                    $anomalousDaysDetails[] = [
                        'employee_id' => $dayData['employee_id'],
                        'employee_name' => $dayData['employee_name'],
                        'date' => $dayData['date'],
                        'hours' => $dayHours,
                        'reason' => $reason,
                        'reason_label' => $reason === 'muy_pocas_horas' 
                            ? 'Muy pocas horas (< ' . round($minThreshold, 2) . 'h)' 
                            : 'Muchas horas (> ' . round($maxThreshold, 2) . 'h)',
                    ];
                }
            }
        }

        // Calcular estadísticas del período anterior
        $previousTotalHours = 0;
        foreach ($previousPeriodEvents as $employeeId => $events) {
            $eventsByDay = $events->groupBy(function ($event) {
                return $event->timestamp->format('Y-m-d');
            });

            foreach ($eventsByDay as $dayEvents) {
                $dayEvents = collect($dayEvents)->sortBy('timestamp')->values();
                for ($i = 0; $i < $dayEvents->count(); $i++) {
                    if ($dayEvents[$i]->event_type === PunchEvent::TYPE_IN) {
                        $nextOut = $dayEvents->slice($i + 1)->firstWhere('event_type', PunchEvent::TYPE_OUT);
                        if ($nextOut) {
                            $sessionMinutes = $dayEvents[$i]->timestamp->diffInMinutes($nextOut->timestamp);
                            $previousTotalHours += round($sessionMinutes / 60, 2);
                        }
                    }
                }
            }
        }

        // Calcular métricas
        $activeEmployeesCount = count($employeeStats);
        $averageHoursPerEmployee = $activeEmployeesCount > 0 ? round($totalHours / $activeEmployeesCount, 2) : 0;
        
        // Contar días únicos con actividad (filtrar claves que terminan en '_sessions')
        $daysWithActivityCount = 0;
        $totalSessionsAllDays = 0;
        foreach ($totalDaysWithActivity as $key => $value) {
            if (substr($key, -9) === '_sessions') {
                // Es una clave de sesiones, sumar al total
                $totalSessionsAllDays += $value;
            } else {
                // Es una clave de día, contar
                $daysWithActivityCount++;
            }
        }
        $averageDaysPerEmployee = $activeEmployeesCount > 0 ? round($daysWithActivityCount / $activeEmployeesCount, 2) : 0;
        
        // Calcular promedio de sesiones por día
        $averageSessionsPerDay = $daysWithActivityCount > 0 ? round($totalSessionsAllDays / $daysWithActivityCount, 2) : 0;
        
        // Variación respecto al período anterior
        $hoursVariation = 0;
        $hoursVariationPercentage = 0;
        if ($previousTotalHours > 0) {
            $hoursVariation = round($totalHours - $previousTotalHours, 2);
            $hoursVariationPercentage = round(($hoursVariation / $previousTotalHours) * 100, 2);
        } elseif ($totalHours > 0) {
            $hoursVariation = $totalHours;
            $hoursVariationPercentage = 100;
        }

        // Métricas derivadas
        $averageHoursPerWorkDay = $daysWithActivityCount > 0 ? round($totalHours / $daysWithActivityCount, 2) : 0;
        $maxHoursDifference = !empty($employeeHours) ? round(max($employeeHours) - min($employeeHours), 2) : 0;
        $closedSessionsPercentage = $totalSessionsCount > 0 
            ? round(($closedSessionsCount / $totalSessionsCount) * 100, 2) 
            : 0;

        // Top y Bottom trabajadores por horas
        $topEmployees = [];
        $bottomEmployees = [];
        if (!empty($employeeStats)) {
            usort($employeeStats, function ($a, $b) {
                return $b['total_hours'] <=> $a['total_hours'];
            });
            $topEmployees = array_slice($employeeStats, 0, 3); // Top 3
            $bottomEmployees = array_slice(array_reverse($employeeStats), 0, 3); // Bottom 3
        }

        // Días más y menos activos
        $mostActiveDays = [];
        $leastActiveDays = [];
        if (!empty($dayActivityDetails)) {
            $daysArray = array_values($dayActivityDetails);
            usort($daysArray, function ($a, $b) {
                return $b['sessions'] <=> $a['sessions'];
            });
            $mostActiveDays = array_slice($daysArray, 0, 3); // Top 3
            $leastActiveDays = array_slice(array_reverse($daysArray), 0, 3); // Bottom 3
        }

        // Trabajadores más activos (por sesiones)
        $mostActiveEmployees = [];
        if (!empty($employeeActivityDetails)) {
            usort($employeeActivityDetails, function ($a, $b) {
                return $b['sessions'] <=> $a['sessions'];
            });
            $mostActiveEmployees = array_slice($employeeActivityDetails, 0, 3); // Top 3
        }

        return response()->json([
            'message' => 'Estadísticas obtenidas correctamente.',
            'data' => [
                'period' => [
                    'label' => $periodLabel,
                    'type' => $period,
                    'date_start' => $dateStart->format('Y-m-d'),
                    'date_end' => $dateEnd->format('Y-m-d'),
                ],
                'definitions' => [
                    'incident' => [
                        'title' => 'Incidencia',
                        'description' => 'Jornada incompleta: entrada sin salida. Es un problema operativo que requiere atención.',
                        'type' => 'operational_issue',
                    ],
                    'anomaly' => [
                        'title' => 'Anomalía',
                        'description' => 'Jornada cerrada pero con duración fuera de lo normal (menos del 50% o más del 200% de la media). Es un dato estadístico sospechoso.',
                        'type' => 'statistical_alert',
                    ],
                ],
                'work' => [
                    'total_hours' => round($totalHours, 2),
                    'average_hours_per_employee' => $averageHoursPerEmployee,
                    'hours_variation' => $hoursVariation,
                    'hours_variation_percentage' => $hoursVariationPercentage,
                    'previous_period_hours' => round($previousTotalHours, 2),
                    'breakdown' => [
                        'top_employees' => array_map(function ($emp) {
                            return [
                                'employee_id' => $emp['employee_id'],
                                'employee_name' => $emp['employee_name'],
                                'total_hours' => $emp['total_hours'],
                            ];
                        }, $topEmployees),
                        'bottom_employees' => array_map(function ($emp) {
                            return [
                                'employee_id' => $emp['employee_id'],
                                'employee_name' => $emp['employee_name'],
                                'total_hours' => $emp['total_hours'],
                            ];
                        }, $bottomEmployees),
                    ],
                ],
                'activity' => [
                    'days_with_activity' => $daysWithActivityCount,
                    'average_days_per_employee' => $averageDaysPerEmployee,
                    'average_sessions_per_day' => $averageSessionsPerDay,
                    'breakdown' => [
                        'most_active_days' => $mostActiveDays,
                        'least_active_days' => $leastActiveDays,
                        'most_active_employees' => $mostActiveEmployees,
                    ],
                ],
                'incidents' => [
                    'open_incidents_count' => count($incidentsDetails),
                    'details' => $incidentsDetails,
                ],
                'anomalies' => [
                    'anomalous_days_count' => $anomalousDaysCount,
                    'details' => $anomalousDaysDetails,
                ],
                'context' => [
                    'active_employees_count' => $activeEmployeesCount,
                    'total_employees_count' => $allEmployees->count(),
                ],
                'derived_metrics' => [
                    'average_hours_per_work_day' => $averageHoursPerWorkDay,
                    'max_hours_difference_between_employees' => $maxHoursDifference,
                    'closed_sessions_percentage' => $closedSessionsPercentage,
                ],
            ],
        ]);
    }

    /**
     * Retorna una respuesta vacía para estadísticas cuando no hay datos.
     */
    private function getEmptyStatisticsResponse(string $periodLabel, Carbon $dateStart, Carbon $dateEnd, string $period): array
    {
        return [
            'period' => [
                'label' => $periodLabel,
                'type' => $period,
                'date_start' => $dateStart->format('Y-m-d'),
                'date_end' => $dateEnd->format('Y-m-d'),
            ],
            'definitions' => [
                'incident' => [
                    'title' => 'Incidencia',
                    'description' => 'Jornada incompleta: entrada sin salida. Es un problema operativo que requiere atención.',
                    'type' => 'operational_issue',
                ],
                'anomaly' => [
                    'title' => 'Anomalía',
                    'description' => 'Jornada cerrada pero con duración fuera de lo normal (menos del 50% o más del 200% de la media). Es un dato estadístico sospechoso.',
                    'type' => 'statistical_alert',
                ],
            ],
            'work' => [
                'total_hours' => 0,
                'average_hours_per_employee' => 0,
                'hours_variation' => 0,
                'hours_variation_percentage' => 0,
                'previous_period_hours' => 0,
                'breakdown' => [
                    'top_employees' => [],
                    'bottom_employees' => [],
                ],
            ],
            'activity' => [
                'days_with_activity' => 0,
                'average_days_per_employee' => 0,
                'average_sessions_per_day' => 0,
                'breakdown' => [
                    'most_active_days' => [],
                    'least_active_days' => [],
                    'most_active_employees' => [],
                ],
            ],
            'incidents' => [
                'open_incidents_count' => 0,
                'details' => [],
            ],
            'anomalies' => [
                'anomalous_days_count' => 0,
                'details' => [],
            ],
            'context' => [
                'active_employees_count' => 0,
                'total_employees_count' => 0,
            ],
            'derived_metrics' => [
                'average_hours_per_work_day' => 0,
                'max_hours_difference_between_employees' => 0,
                'closed_sessions_percentage' => 0,
            ],
        ];
    }

    /**
     * Formatear minutos en formato legible (ej: "4h 30m" o "45m").
     */
    private function formatTime(int $minutes): string
    {
        if ($minutes === 0) {
            return '0m';
        }
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$mins}m";
        }
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

