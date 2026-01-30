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

        // Extraer todos los filtros del request (soporta estructura anidada)
        $filters = $request->all();

        // Aplicar filtros usando el método helper
        $query = $this->applyFiltersToQuery($query, $filters);

        // Ordenar por timestamp descendente (más recientes primero)
        $query->orderBy('timestamp', 'desc');

        $perPage = $request->input('perPage', 15);
        
        return PunchEventResource::collection($query->paginate($perPage));
    }

    /**
     * Aplicar filtros a la consulta de fichajes.
     * Soporta estructura anidada de filtros como otros controladores genéricos.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyFiltersToQuery($query, $filters)
    {
        // Para aceptar filtros anidados
        if (isset($filters['filters'])) {
            $filters = $filters['filters'];
        }

        // Filtro por ID único
        if (isset($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        // Filtro por múltiples IDs
        if (isset($filters['ids']) && is_array($filters['ids']) && !empty($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        // Filtro por empleado único
        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        // Filtro por múltiples empleados (employee_ids o employees)
        if (isset($filters['employee_ids']) && is_array($filters['employee_ids']) && !empty($filters['employee_ids'])) {
            $query->whereIn('employee_id', $filters['employee_ids']);
        } elseif (isset($filters['employees']) && is_array($filters['employees']) && !empty($filters['employees'])) {
            $query->whereIn('employee_id', $filters['employees']);
        }

        // Filtro por tipo de evento
        if (isset($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        // Filtro por dispositivo único
        if (isset($filters['device_id'])) {
            $query->where('device_id', $filters['device_id']);
        }

        // Filtro por múltiples dispositivos (devices)
        if (isset($filters['devices']) && is_array($filters['devices']) && !empty($filters['devices'])) {
            $query->whereIn('device_id', $filters['devices']);
        }

        // Filtro por rango de fechas (dates con start y end)
        if (isset($filters['dates'])) {
            $dates = $filters['dates'];
            if (isset($dates['start'])) {
                try {
                    $dateStart = Carbon::parse($dates['start'])->startOfDay();
                    $query->where('timestamp', '>=', $dateStart);
                } catch (\Exception $e) {
                    // Ignorar si la fecha no es válida
                }
            }
            if (isset($dates['end'])) {
                try {
                    $dateEnd = Carbon::parse($dates['end'])->endOfDay();
                    $query->where('timestamp', '<=', $dateEnd);
                } catch (\Exception $e) {
                    // Ignorar si la fecha no es válida
                }
            }
        }

        // Filtro por rango de fechas (date_start y date_end) - compatibilidad con formato anterior
        if (isset($filters['date_start'])) {
            try {
                $dateStart = Carbon::parse($filters['date_start'])->startOfDay();
                $query->where('timestamp', '>=', $dateStart);
            } catch (\Exception $e) {
                // Ignorar si la fecha no es válida
            }
        }

        if (isset($filters['date_end'])) {
            try {
                $dateEnd = Carbon::parse($filters['date_end'])->endOfDay();
                $query->where('timestamp', '<=', $dateEnd);
            } catch (\Exception $e) {
                // Ignorar si la fecha no es válida
            }
        }

        // Filtro por rango de timestamps (más preciso)
        if (isset($filters['timestamp_start'])) {
            try {
                $timestampStart = Carbon::parse($filters['timestamp_start']);
                $query->where('timestamp', '>=', $timestampStart);
            } catch (\Exception $e) {
                // Ignorar si la fecha no es válida
            }
        }

        if (isset($filters['timestamp_end'])) {
            try {
                $timestampEnd = Carbon::parse($filters['timestamp_end']);
                $query->where('timestamp', '<=', $timestampEnd);
            } catch (\Exception $e) {
                // Ignorar si la fecha no es válida
            }
        }

        // Filtro por día específico (shortcut)
        if (isset($filters['date'])) {
            try {
                $date = Carbon::parse($filters['date']);
                $query->whereDate('timestamp', $date->toDateString());
            } catch (\Exception $e) {
                // Ignorar si la fecha no es válida
            }
        }

        return $query;
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
     * Detecta automáticamente si es un fichaje manual (con timestamp y event_type) o NFC.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Detectar si es un fichaje manual (tiene timestamp y event_type)
        if ($request->has('timestamp') && $request->has('event_type')) {
            // Es un fichaje manual, redirigir al método correspondiente
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

        // Detectar faltas de fichaje en días anteriores (entradas sin salida y salidas sin entrada)
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
                
                // Si no hay OUT, es una sesión abierta (falta de fichaje: entrada sin salida)
                if (!$hasOut) {
                    // Verificar si no está ya en la lista para este empleado y día
                    $key = $employeeId . '_' . $eventDate->format('Y-m-d') . '_entry';
                    
                    if (!isset($missingPunches[$key])) {
                        $daysAgo = now()->startOfDay()->diffInDays($eventDate);
                        
                        $missingPunches[$key] = [
                            'employeeId' => $employee->id,
                            'employeeName' => $employee->name,
                            'nfcUid' => $employee->nfc_uid,
                            'date' => $eventDate->format('Y-m-d'),
                            'daysAgo' => $daysAgo,
                            'type' => 'entry_without_exit',
                            'typeLabel' => 'Entrada sin salida',
                            'entryTimestamp' => $inEvent->timestamp->format('Y-m-d H:i:s'),
                            'entryDeviceId' => $inEvent->device_id,
                            'hoursOpen' => round(now()->diffInHours($inEvent->timestamp), 1),
                        ];
                    }
                }
            }
        }

        // Buscar todos los eventos OUT de días anteriores (excluyendo hoy) sin entrada previa
        $pastOutEvents = PunchEvent::where('event_type', PunchEvent::TYPE_OUT)
            ->where('timestamp', '<', $date)
            ->where('timestamp', '>=', $yesterday->copy()->subDays(30)) // Últimos 30 días
            ->with('employee')
            ->orderBy('timestamp', 'desc')
            ->get()
            ->groupBy('employee_id');

        foreach ($pastOutEvents as $employeeId => $outEvents) {
            $employee = $outEvents->first()->employee;
            
            foreach ($outEvents as $outEvent) {
                $eventDate = $outEvent->timestamp->startOfDay();
                $eventDateEnd = $eventDate->copy()->endOfDay();
                
                // Verificar si existe un IN en el mismo día antes de este OUT
                $hasIn = PunchEvent::where('employee_id', $employeeId)
                    ->where('event_type', PunchEvent::TYPE_IN)
                    ->where('timestamp', '<', $outEvent->timestamp)
                    ->whereBetween('timestamp', [$eventDate, $eventDateEnd])
                    ->exists();
                
                // Si no hay IN, es una incidencia (salida sin entrada)
                if (!$hasIn) {
                    // Verificar si no está ya en la lista para este empleado y día
                    $key = $employeeId . '_' . $eventDate->format('Y-m-d') . '_exit';
                    
                    if (!isset($missingPunches[$key])) {
                        $daysAgo = now()->startOfDay()->diffInDays($eventDate);
                        
                        $missingPunches[$key] = [
                            'employeeId' => $employee->id,
                            'employeeName' => $employee->name,
                            'nfcUid' => $employee->nfc_uid,
                            'date' => $eventDate->format('Y-m-d'),
                            'daysAgo' => $daysAgo,
                            'type' => 'exit_without_entry',
                            'typeLabel' => 'Salida sin entrada',
                            'exitTimestamp' => $outEvent->timestamp->format('Y-m-d H:i:s'),
                            'exitDeviceId' => $outEvent->device_id,
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
     * Obtener fichajes agrupados por día para un calendario.
     * Incluye incidencias (entradas sin salida y salidas sin entrada) y anomalías (jornadas con horas anómalas).
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calendar(Request $request)
    {
        // Obtener año y mes de los parámetros (por defecto: año y mes actual)
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        // Validar año y mes
        if (!is_numeric($year) || $year < 2000 || $year > 2100) {
            return response()->json([
                'message' => 'El año debe ser un número válido entre 2000 y 2100.',
                'userMessage' => 'El año proporcionado no es válido.',
                'error' => 'INVALID_YEAR',
            ], 400);
        }

        if (!is_numeric($month) || $month < 1 || $month > 12) {
            return response()->json([
                'message' => 'El mes debe ser un número válido entre 1 y 12.',
                'userMessage' => 'El mes proporcionado no es válido.',
                'error' => 'INVALID_MONTH',
            ], 400);
        }

        // Crear fechas de inicio y fin del mes
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        // Obtener todos los fichajes del mes con la relación de empleado
        $punches = PunchEvent::whereBetween('timestamp', [$startDate, $endDate])
            ->with('employee')
            ->orderBy('timestamp', 'asc')
            ->get();

        // Agrupar fichajes por día y por empleado para detectar incidencias y anomalías
        $punchesByDay = [];
        $allDayHours = []; // Para calcular media y detectar anomalías

        // Primero, agrupar por empleado y luego por día
        $punchesByEmployee = $punches->groupBy('employee_id');

        foreach ($punchesByEmployee as $employeeId => $employeePunches) {
            // Agrupar eventos del empleado por día
            $eventsByDay = $employeePunches->groupBy(function ($event) {
                return $event->timestamp->format('Y-m-d');
            });

            foreach ($eventsByDay as $dayKey => $dayEvents) {
                $day = Carbon::parse($dayKey)->day;
                $dayStart = Carbon::parse($dayKey)->startOfDay();
                $dayEnd = Carbon::parse($dayKey)->endOfDay();

                // Ordenar eventos del día por timestamp
                $dayEvents = $dayEvents->sortBy('timestamp')->values();

                // Inicializar array del día si no existe
                if (!isset($punchesByDay[$day])) {
                    $punchesByDay[$day] = [
                        'punches' => [],
                        'incidents' => [],
                        'anomalies' => [],
                    ];
                }

                // Agregar fichajes del día
                foreach ($dayEvents as $punch) {
                    $punchesByDay[$day]['punches'][] = [
                        'id' => $punch->id,
                        'employee_id' => $punch->employee_id,
                        'employee_name' => $punch->employee->name ?? '',
                        'event_type' => $punch->event_type,
                        'timestamp' => $punch->timestamp->format('Y-m-d H:i:s'),
                        'device_id' => $punch->device_id,
                    ];
                }

                // Detectar incidencias (entradas sin salida y salidas sin entrada)
                $dayMinutes = 0;
                $hasOpenSession = false;
                $openSessionEntry = null;
                $hasOrphanExit = false;
                $orphanExit = null;

                // Verificar si el primer evento del día es una salida (sin entrada previa)
                if ($dayEvents->isNotEmpty() && $dayEvents->first()->event_type === PunchEvent::TYPE_OUT) {
                    $hasOrphanExit = true;
                    $orphanExit = $dayEvents->first();
                }

                for ($i = 0; $i < $dayEvents->count(); $i++) {
                    if ($dayEvents[$i]->event_type === PunchEvent::TYPE_IN) {
                        $nextOut = $dayEvents->slice($i + 1)->firstWhere('event_type', PunchEvent::TYPE_OUT);
                        
                        if ($nextOut) {
                            // Sesión completa
                            $sessionMinutes = $dayEvents[$i]->timestamp->diffInMinutes($nextOut->timestamp);
                            $dayMinutes += $sessionMinutes;
                        } else {
                            // Sesión abierta (incidencia: entrada sin salida)
                            $hasOpenSession = true;
                            $openSessionEntry = $dayEvents[$i];
                        }
                    } elseif ($dayEvents[$i]->event_type === PunchEvent::TYPE_OUT) {
                        // Verificar si hay una entrada previa en el mismo día
                        $prevIn = $dayEvents->slice(0, $i)->reverse()->firstWhere('event_type', PunchEvent::TYPE_IN);
                        if (!$prevIn) {
                            // Salida sin entrada previa en el mismo día (incidencia)
                            $hasOrphanExit = true;
                            $orphanExit = $dayEvents[$i];
                        }
                    }
                }

                // Registrar incidencia de entrada sin salida
                if ($hasOpenSession && $openSessionEntry) {
                    $punchesByDay[$day]['incidents'][] = [
                        'employee_id' => $openSessionEntry->employee_id,
                        'employee_name' => $openSessionEntry->employee->name ?? '',
                        'type' => 'entry_without_exit',
                        'type_label' => 'Entrada sin salida',
                        'entry_timestamp' => $openSessionEntry->timestamp->format('Y-m-d H:i:s'),
                        'entry_time' => $openSessionEntry->timestamp->format('H:i:s'),
                        'device_id' => $openSessionEntry->device_id,
                    ];
                }

                // Registrar incidencia de salida sin entrada
                if ($hasOrphanExit && $orphanExit) {
                    $punchesByDay[$day]['incidents'][] = [
                        'employee_id' => $orphanExit->employee_id,
                        'employee_name' => $orphanExit->employee->name ?? '',
                        'type' => 'exit_without_entry',
                        'type_label' => 'Salida sin entrada',
                        'exit_timestamp' => $orphanExit->timestamp->format('Y-m-d H:i:s'),
                        'exit_time' => $orphanExit->timestamp->format('H:i:s'),
                        'device_id' => $orphanExit->device_id,
                    ];
                }

                // Registrar horas del día para calcular anomalías (solo si hay sesiones completas)
                if ($dayMinutes > 0) {
                    $dayHours = round($dayMinutes / 60, 2);
                    $allDayHours[] = [
                        'hours' => $dayHours,
                        'employee_id' => $employeePunches->first()->employee_id,
                        'employee_name' => $employeePunches->first()->employee->name ?? '',
                        'date' => $dayKey,
                        'day' => $day,
                    ];
                }
            }
        }

        // Calcular media de horas por jornada para detectar anomalías
        $averageHoursPerDay = 0;
        $minThreshold = 0;
        $maxThreshold = 0;

        if (!empty($allDayHours)) {
            $dayHoursValues = array_column($allDayHours, 'hours');
            $averageHoursPerDay = array_sum($dayHoursValues) / count($dayHoursValues);
            // Considerar anómalas las jornadas que son menos del 50% o más del 200% de la media
            $minThreshold = $averageHoursPerDay * 0.5;
            $maxThreshold = $averageHoursPerDay * 2.0;

            // Detectar anomalías
            foreach ($allDayHours as $dayData) {
                $dayHours = $dayData['hours'];
                if ($dayHours < $minThreshold || $dayHours > $maxThreshold) {
                    $day = $dayData['day'];
                    $reason = $dayHours < $minThreshold ? 'muy_pocas_horas' : 'muchas_horas';
                    
                    if (!isset($punchesByDay[$day])) {
                        $punchesByDay[$day] = [
                            'punches' => [],
                            'incidents' => [],
                            'anomalies' => [],
                        ];
                    }

                    $punchesByDay[$day]['anomalies'][] = [
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

        // Limpiar estructura: si un día no tiene fichajes, incidencias ni anomalías, no incluirlo
        // Pero mantener la estructura para días que sí tienen datos
        foreach ($punchesByDay as $day => $dayData) {
            if (empty($dayData['punches']) && empty($dayData['incidents']) && empty($dayData['anomalies'])) {
                unset($punchesByDay[$day]);
            }
        }

        // Contar totales
        $totalPunches = $punches->count();
        $totalEmployees = $punches->pluck('employee_id')->unique()->count();

        return response()->json([
            'data' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'punches_by_day' => $punchesByDay,
                'total_punches' => $totalPunches,
                'total_employees' => $totalEmployees,
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
        // IMPORTANTE: Las incidencias (entradas sin salida y salidas sin entrada) NO contaminan las estadísticas
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
                $hasOrphanExit = false;
                $orphanExit = null;

                // Verificar si el primer evento del día es una salida (sin entrada previa)
                if ($dayEvents->isNotEmpty() && $dayEvents->first()->event_type === PunchEvent::TYPE_OUT) {
                    $hasOrphanExit = true;
                    $orphanExit = $dayEvents->first();
                }

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
                            // Sesión abierta (incidencia: entrada sin salida) - NO cuenta en estadísticas
                            $hasOpenSession = true;
                            $openSessionEntry = $dayEvents[$i];
                            // NO sumamos minutos ni sesiones a las estadísticas
                        }
                        $totalSessionsCount++;
                    } elseif ($dayEvents[$i]->event_type === PunchEvent::TYPE_OUT) {
                        // Verificar si hay una entrada previa en el mismo día
                        $prevIn = $dayEvents->slice(0, $i)->reverse()->firstWhere('event_type', PunchEvent::TYPE_IN);
                        if (!$prevIn) {
                            // Salida sin entrada previa en el mismo día (incidencia)
                            $hasOrphanExit = true;
                            $orphanExit = $dayEvents[$i];
                        }
                    }
                }

                // Registrar incidencia de entrada sin salida
                if ($hasOpenSession && $openSessionEntry) {
                    $incidentsDetails[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'date' => $day,
                        'type' => 'entry_without_exit',
                        'type_label' => 'Entrada sin salida',
                        'entry_timestamp' => $openSessionEntry->timestamp->format('Y-m-d H:i:s'),
                        'entry_time' => $openSessionEntry->timestamp->format('H:i:s'),
                        'device_id' => $openSessionEntry->device_id,
                    ];
                }

                // Registrar incidencia de salida sin entrada
                if ($hasOrphanExit && $orphanExit) {
                    $incidentsDetails[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'date' => $day,
                        'type' => 'exit_without_entry',
                        'type_label' => 'Salida sin entrada',
                        'exit_timestamp' => $orphanExit->timestamp->format('Y-m-d H:i:s'),
                        'exit_time' => $orphanExit->timestamp->format('H:i:s'),
                        'device_id' => $orphanExit->device_id,
                    ];
                }

                // Solo contar días con sesiones completas en estadísticas
                if ($dayMinutes > 0) {
                    $dayHours = round($dayMinutes / 60, 2);
                    $employeeTotalMinutes += $dayMinutes;
                    $employeeDaysWithActivity++;
                    // Marcar día como activo (usar true para contar días únicos)
                    $totalDaysWithActivity[$day] = true;
                    
                    $employeeSessions += $daySessions;
                    $employeeClosedSessions += $dayClosedSessions;
                    $allDayHours[] = [
                        'hours' => $dayHours,
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'date' => $day,
                    ];

                    // Registrar actividad por día (basada en horas trabajadas)
                    if (!isset($dayActivityDetails[$day])) {
                        $dayActivityDetails[$day] = [
                            'date' => $day,
                            'total_hours' => 0,
                            'employees' => [],
                        ];
                    }
                    $dayActivityDetails[$day]['total_hours'] += $dayHours;
                    // Agregar empleado con sus horas del día
                    $dayActivityDetails[$day]['employees'][] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'hours' => $dayHours,
                    ];
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
        
        // Contar días únicos con actividad
        $daysWithActivityCount = count($totalDaysWithActivity);
        // Calcular promedio de horas por día (en lugar de sesiones)
        $averageHoursPerDay = $daysWithActivityCount > 0 ? round($totalHours / $daysWithActivityCount, 2) : 0;
        
        // Calcular promedio de horas por día de media por trabajador
        // Para cada trabajador: horas totales / días trabajados, luego media de todos
        $averageHoursPerDayPerEmployee = 0;
        if (!empty($employeeStats)) {
            $hoursPerDayByEmployee = [];
            foreach ($employeeStats as $employee) {
                if ($employee['days_with_activity'] > 0) {
                    $hoursPerDayByEmployee[] = $employee['total_hours'] / $employee['days_with_activity'];
                }
            }
            if (!empty($hoursPerDayByEmployee)) {
                $averageHoursPerDayPerEmployee = round(array_sum($hoursPerDayByEmployee) / count($hoursPerDayByEmployee), 2);
            }
        }
        
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

        // Top y Bottom trabajadores por horas
        $topEmployees = [];
        $bottomEmployees = [];
        if (!empty($employeeStats)) {
            usort($employeeStats, function ($a, $b) {
                return $b['total_hours'] <=> $a['total_hours'];
            });
            
            $totalEmployees = count($employeeStats);
            
            if ($totalEmployees < 6) {
                // Si hay menos de 6, dividir equitativamente
                $topCount = (int) ceil($totalEmployees / 2);
                $bottomCount = $totalEmployees - $topCount;
                
                $topEmployees = array_slice($employeeStats, 0, $topCount);
                $bottomEmployees = array_slice($employeeStats, -$bottomCount);
                // Invertir el orden del bottom para que el de menos horas aparezca primero
                $bottomEmployees = array_reverse($bottomEmployees);
            } else {
                // Si hay 6 o más, top 3 y bottom 3 sin solapamiento
                $topEmployees = array_slice($employeeStats, 0, 3);
                
                // Obtener IDs de los top employees para evitar duplicados
                $topEmployeeIds = array_column($topEmployees, 'employee_id');
                
                // Filtrar bottom employees excluyendo los que ya están en top
                $bottomCandidates = array_reverse($employeeStats);
                $bottomEmployees = [];
                foreach ($bottomCandidates as $employee) {
                    if (!in_array($employee['employee_id'], $topEmployeeIds)) {
                        $bottomEmployees[] = $employee;
                        if (count($bottomEmployees) >= 3) {
                            break;
                        }
                    }
                }
            }
        }

        // Días más y menos activos (basados en horas trabajadas)
        $mostActiveDays = [];
        $leastActiveDays = [];
        if (!empty($dayActivityDetails)) {
            $daysArray = array_values($dayActivityDetails);
            // Calcular número de empleados y promedio de horas por trabajador para cada día
            foreach ($daysArray as &$day) {
                $day['employees_count'] = count($day['employees']);
                // Calcular horas medias por trabajador (reemplaza total_hours)
                $day['average_hours_per_employee'] = $day['employees_count'] > 0 
                    ? round($day['total_hours'] / $day['employees_count'], 2) 
                    : 0;
                // Eliminar total_hours ya que ahora usamos average_hours_per_employee
                unset($day['total_hours']);
            }
            unset($day);
            
            // Ordenar por horas medias por trabajador
            usort($daysArray, function ($a, $b) {
                return $b['average_hours_per_employee'] <=> $a['average_hours_per_employee'];
            });
            $mostActiveDays = array_slice($daysArray, 0, 3); // Top 3
            $leastActiveDays = array_slice(array_reverse($daysArray), 0, 3); // Bottom 3
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
                    'average_hours_per_day' => $averageHoursPerDay,
                    'average_hours_per_day_per_employee' => $averageHoursPerDayPerEmployee,
                    'breakdown' => [
                        'most_active_days' => $mostActiveDays,
                        'least_active_days' => $leastActiveDays,
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
                'average_hours_per_day' => 0,
                'average_hours_per_day_per_employee' => 0,
                'breakdown' => [
                    'most_active_days' => [],
                    'least_active_days' => [],
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

    /**
     * Crear un fichaje manual individual.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeManual(Request $request)
    {
        // Los fichajes manuales requieren autenticación
        if (!$request->user()) {
            return response()->json([
                'message' => 'Error al crear fichaje',
                'userMessage' => 'Los fichajes manuales requieren autenticación.',
                'errors' => [
                    'auth' => ['Se requiere autenticación para crear fichajes manuales.'],
                ],
            ], 401);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:tenant.employees,id',
            'event_type' => 'required|in:IN,OUT',
            'timestamp' => 'required|date',
            'device_id' => 'nullable|string',
        ], [
            'employee_id.required' => 'El ID del empleado es obligatorio.',
            'employee_id.integer' => 'El ID del empleado debe ser un número entero.',
            'employee_id.exists' => 'El empleado especificado no existe.',
            'event_type.required' => 'El tipo de evento es obligatorio.',
            'event_type.in' => 'El tipo de evento debe ser IN o OUT.',
            'timestamp.required' => 'El timestamp es obligatorio.',
            'timestamp.date' => 'El timestamp debe ser una fecha válida en formato ISO 8601.',
        ]);

        // Usar device_id proporcionado o "manual-admin" por defecto
        $deviceId = $validated['device_id'] ?? 'manual-admin';

        // Parsear timestamp
        try {
            $timestamp = Carbon::parse($validated['timestamp']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear fichaje',
                'userMessage' => 'El formato de fecha es inválido. Use formato ISO 8601 (ejemplo: 2024-01-15T08:30:00.000Z).',
                'errors' => [
                    'timestamp' => ['El formato de fecha es inválido.'],
                ],
            ], 422);
        }

        // Validar que no sea fecha futura
        if ($timestamp->isFuture()) {
            return response()->json([
                'message' => 'Error al crear fichaje',
                'userMessage' => 'No se pueden registrar fichajes con fechas futuras.',
                'errors' => [
                    'timestamp' => ['No se permiten fechas futuras.'],
                ],
            ], 422);
        }

        // Obtener empleado
        $employee = Employee::find($validated['employee_id']);
        if (!$employee) {
            return response()->json([
                'message' => 'Error al crear fichaje',
                'userMessage' => 'El empleado especificado no existe.',
                'errors' => [
                    'employee_id' => ['El empleado especificado no existe.'],
                ],
            ], 422);
        }

        // Validar integridad de secuencia
        $validationResult = $this->validatePunchSequence($employee, $validated['event_type'], $timestamp);
        if (!$validationResult['valid']) {
            return response()->json([
                'message' => 'Error al crear fichaje',
                'userMessage' => $validationResult['userMessage'],
                'errors' => $validationResult['errors'],
            ], 422);
        }

        // Verificar duplicados
        $duplicate = PunchEvent::where('employee_id', $employee->id)
            ->where('event_type', $validated['event_type'])
            ->where('timestamp', $timestamp->format('Y-m-d H:i:s'))
            ->first();

        if ($duplicate) {
            return response()->json([
                'message' => 'Error al crear fichaje',
                'userMessage' => 'Ya existe un fichaje con los mismos datos (empleado, tipo y fecha/hora).',
                'errors' => [
                    'timestamp' => ['Ya existe un fichaje duplicado.'],
                ],
            ], 422);
        }

        // Crear el fichaje en transacción
        try {
            DB::beginTransaction();

            $punchEvent = PunchEvent::create([
                'employee_id' => $employee->id,
                'event_type' => $validated['event_type'],
                'device_id' => $deviceId,
                'timestamp' => $timestamp,
            ]);

            DB::commit();

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

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al crear fichaje',
                'userMessage' => 'Ocurrió un error inesperado al registrar el fichaje.',
                'errors' => [],
            ], 500);
        }
    }

    /**
     * Validar múltiples fichajes antes de crearlos.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkValidate(Request $request)
    {
        // Los fichajes manuales requieren autenticación
        if (!$request->user()) {
            return response()->json([
                'message' => 'Error al validar fichajes',
                'userMessage' => 'La validación de fichajes manuales requiere autenticación.',
                'errors' => [
                    'auth' => ['Se requiere autenticación para validar fichajes manuales.'],
                ],
            ], 401);
        }

        $validated = $request->validate([
            'punches' => 'required|array|min:1',
            'punches.*.employee_id' => 'required|integer|exists:tenant.employees,id',
            'punches.*.event_type' => 'required|in:IN,OUT',
            'punches.*.timestamp' => 'required|date',
            'punches.*.device_id' => 'nullable|string',
        ], [
            'punches.required' => 'Debe proporcionar un array de fichajes.',
            'punches.array' => 'Los fichajes deben ser un array.',
            'punches.min' => 'Debe proporcionar al menos un fichaje.',
            'punches.*.employee_id.required' => 'El ID del empleado es obligatorio.',
            'punches.*.employee_id.integer' => 'El ID del empleado debe ser un número entero.',
            'punches.*.employee_id.exists' => 'El empleado especificado no existe.',
            'punches.*.event_type.required' => 'El tipo de evento es obligatorio.',
            'punches.*.event_type.in' => 'El tipo de evento debe ser IN o OUT.',
            'punches.*.timestamp.required' => 'El timestamp es obligatorio.',
            'punches.*.timestamp.date' => 'El timestamp debe ser una fecha válida.',
        ]);

        $validationResults = [];
        $validCount = 0;
        $invalidCount = 0;

        // Obtener todos los empleados únicos para optimizar consultas
        $employeeIds = collect($validated['punches'])->pluck('employee_id')->unique();
        $employees = Employee::whereIn('id', $employeeIds)->get()->keyBy('id');

        // Validar cada fichaje
        foreach ($validated['punches'] as $index => $punch) {
            $errors = [];
            
            // Parsear timestamp
            try {
                $timestamp = Carbon::parse($punch['timestamp']);
            } catch (\Exception $e) {
                $errors[] = 'El formato de fecha es inválido. Use formato ISO 8601.';
                $validationResults[] = [
                    'index' => $index,
                    'valid' => false,
                    'errors' => $errors,
                ];
                $invalidCount++;
                continue;
            }

            // Validar que no sea fecha futura
            if ($timestamp->isFuture()) {
                $errors[] = 'No se permiten fechas futuras.';
            }

            // Verificar que el empleado existe
            $employee = $employees->get($punch['employee_id']);
            if (!$employee) {
                $errors[] = 'El empleado especificado no existe.';
                $validationResults[] = [
                    'index' => $index,
                    'valid' => false,
                    'errors' => $errors,
                ];
                $invalidCount++;
                continue;
            }

            // Obtener el último fichaje existente en BD que sea ANTERIOR al timestamp actual
            $lastPunchInDb = PunchEvent::where('employee_id', $punch['employee_id'])
                ->where('timestamp', '<', $timestamp)
                ->orderBy('timestamp', 'desc')
                ->first();

            // Buscar el último fichaje del mismo lote (ya validados) que sea anterior
            $lastPunchInBatch = null;
            $lastPunchInBatchTimestamp = null;
            foreach ($validationResults as $prevResult) {
                if ($prevResult['index'] < $index) {
                    $prevPunch = $validated['punches'][$prevResult['index']];
                    if ($prevPunch['employee_id'] === $punch['employee_id']) {
                        try {
                            $prevTimestamp = Carbon::parse($prevPunch['timestamp']);
                            if ($prevTimestamp->lt($timestamp)) {
                                if (!$lastPunchInBatchTimestamp || $prevTimestamp->gt($lastPunchInBatchTimestamp)) {
                                    $lastPunchInBatch = $prevPunch;
                                    $lastPunchInBatchTimestamp = $prevTimestamp;
                                }
                            }
                        } catch (\Exception $e) {
                            // Ignorar si no se puede parsear
                        }
                    }
                }
            }

            // Determinar el último fichaje relevante (el más reciente entre BD y lote)
            $relevantLastPunch = null;
            $relevantLastTimestamp = null;
            
            if ($lastPunchInDb) {
                $relevantLastPunch = $lastPunchInDb;
                $relevantLastTimestamp = $lastPunchInDb->timestamp;
            }
            
            if ($lastPunchInBatch && $lastPunchInBatchTimestamp) {
                if (!$relevantLastTimestamp || $lastPunchInBatchTimestamp->gt($relevantLastTimestamp)) {
                    // El fichaje del lote es más reciente, usar ese
                    $relevantLastPunch = (object)[
                        'event_type' => $lastPunchInBatch['event_type'],
                        'timestamp' => $lastPunchInBatchTimestamp,
                    ];
                    $relevantLastTimestamp = $lastPunchInBatchTimestamp;
                }
            }

            // Validar secuencia lógica con el último fichaje relevante
            if ($relevantLastPunch) {
                if ($relevantLastPunch->event_type === $punch['event_type']) {
                    if ($punch['event_type'] === PunchEvent::TYPE_IN) {
                        $errors[] = 'No se puede registrar una entrada sin una salida previa.';
                        $errors[] = 'El último fichaje del empleado es una entrada.';
                    } else {
                        $errors[] = 'No se puede registrar una salida sin una entrada previa.';
                        $errors[] = 'El último fichaje del empleado es una salida.';
                    }
                } else {
                    // Validar coherencia temporal: OUT debe ser posterior al IN correspondiente
                    if ($punch['event_type'] === PunchEvent::TYPE_OUT && $relevantLastPunch->event_type === PunchEvent::TYPE_IN) {
                        if ($timestamp->lte($relevantLastTimestamp)) {
                            $errors[] = 'La salida debe ser posterior a la entrada correspondiente.';
                        }
                    }
                }
            }

            // Verificar duplicados con fichajes existentes
            $duplicate = PunchEvent::where('employee_id', $punch['employee_id'])
                ->where('event_type', $punch['event_type'])
                ->where('timestamp', $timestamp->format('Y-m-d H:i:s'))
                ->exists();

            if ($duplicate) {
                $errors[] = 'Ya existe un fichaje con los mismos datos (empleado, tipo y fecha/hora).';
            }

            // Validar contra otros fichajes del mismo lote (orden cronológico)
            // Verificar que no haya fichajes del mismo tipo sin el opuesto intermedio
            foreach ($validationResults as $prevResult) {
                if ($prevResult['index'] < $index) {
                    $prevPunch = $validated['punches'][$prevResult['index']];
                    if ($prevPunch['employee_id'] === $punch['employee_id']) {
                        // Mismo empleado, verificar orden
                        try {
                            $prevTimestamp = Carbon::parse($prevPunch['timestamp']);
                            // Si el fichaje previo es del mismo tipo y es más reciente que el último relevante
                            if ($prevPunch['event_type'] === $punch['event_type'] && 
                                $prevTimestamp->gt($timestamp)) {
                                // Hay un fichaje del mismo tipo más adelante en el tiempo
                                // Esto es un error de orden
                                $errors[] = 'Hay un fichaje del mismo tipo con fecha posterior en el mismo lote.';
                            }
                            // Validar que OUT sea posterior a IN en el mismo lote
                            if ($punch['event_type'] === PunchEvent::TYPE_OUT && $prevPunch['event_type'] === PunchEvent::TYPE_IN) {
                                if ($timestamp->lte($prevTimestamp)) {
                                    $errors[] = 'La salida debe ser posterior a la entrada en el mismo lote.';
                                }
                            }
                        } catch (\Exception $e) {
                            // Ignorar si no se puede parsear (ya tiene error)
                        }
                    }
                }
            }

            $isValid = empty($errors);
            if ($isValid) {
                $validCount++;
            } else {
                $invalidCount++;
            }

            $validationResults[] = [
                'index' => $index,
                'valid' => $isValid,
                'errors' => $errors,
            ];
        }

        return response()->json([
            'data' => [
                'valid' => $validCount,
                'invalid' => $invalidCount,
                'validation_results' => $validationResults,
            ],
        ]);
    }

    /**
     * Crear múltiples fichajes de forma masiva.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkStore(Request $request)
    {
        // Los fichajes manuales requieren autenticación
        if (!$request->user()) {
            return response()->json([
                'message' => 'Error al crear fichajes masivos',
                'userMessage' => 'La creación de fichajes manuales requiere autenticación.',
                'errors' => [
                    'auth' => ['Se requiere autenticación para crear fichajes manuales.'],
                ],
            ], 401);
        }

        $validated = $request->validate([
            'punches' => 'required|array|min:1',
            'punches.*.employee_id' => 'required|integer|exists:tenant.employees,id',
            'punches.*.event_type' => 'required|in:IN,OUT',
            'punches.*.timestamp' => 'required|date',
            'punches.*.device_id' => 'nullable|string',
        ], [
            'punches.required' => 'Debe proporcionar un array de fichajes.',
            'punches.array' => 'Los fichajes deben ser un array.',
            'punches.min' => 'Debe proporcionar al menos un fichaje.',
            'punches.*.employee_id.required' => 'El ID del empleado es obligatorio.',
            'punches.*.employee_id.integer' => 'El ID del empleado debe ser un número entero.',
            'punches.*.employee_id.exists' => 'El empleado especificado no existe.',
            'punches.*.event_type.required' => 'El tipo de evento es obligatorio.',
            'punches.*.event_type.in' => 'El tipo de evento debe ser IN o OUT.',
            'punches.*.timestamp.required' => 'El timestamp es obligatorio.',
            'punches.*.timestamp.date' => 'El timestamp debe ser una fecha válida.',
        ]);

        // Primero validar todos los fichajes
        $validationResults = [];
        $validPunches = [];
        $invalidPunches = [];

        // Obtener todos los empleados únicos para optimizar consultas
        $employeeIds = collect($validated['punches'])->pluck('employee_id')->unique();
        $employees = Employee::whereIn('id', $employeeIds)->get()->keyBy('id');

        // Validar cada fichaje
        foreach ($validated['punches'] as $index => $punch) {
            $errors = [];
            
            // Parsear timestamp
            try {
                $timestamp = Carbon::parse($punch['timestamp']);
            } catch (\Exception $e) {
                $errors[] = 'El formato de fecha es inválido. Use formato ISO 8601.';
                $invalidPunches[] = [
                    'index' => $index,
                    'punch' => $punch,
                    'errors' => $errors,
                ];
                continue;
            }

            // Validar que no sea fecha futura
            if ($timestamp->isFuture()) {
                $errors[] = 'No se permiten fechas futuras.';
            }

            // Verificar que el empleado existe
            $employee = $employees->get($punch['employee_id']);
            if (!$employee) {
                $errors[] = 'El empleado especificado no existe.';
                $invalidPunches[] = [
                    'index' => $index,
                    'punch' => $punch,
                    'errors' => $errors,
                ];
                continue;
            }

            // Obtener el último fichaje existente en BD que sea ANTERIOR al timestamp actual
            $lastPunchInDb = PunchEvent::where('employee_id', $punch['employee_id'])
                ->where('timestamp', '<', $timestamp)
                ->orderBy('timestamp', 'desc')
                ->first();

            // Buscar el último fichaje del mismo lote (ya validados) que sea anterior
            $lastPunchInBatch = null;
            $lastPunchInBatchTimestamp = null;
            foreach ($validPunches as $prevValid) {
                $prevPunch = $prevValid['punch'];
                if ($prevPunch['employee_id'] === $punch['employee_id']) {
                    try {
                        $prevTimestamp = Carbon::parse($prevPunch['timestamp']);
                        if ($prevTimestamp->lt($timestamp)) {
                            if (!$lastPunchInBatchTimestamp || $prevTimestamp->gt($lastPunchInBatchTimestamp)) {
                                $lastPunchInBatch = $prevPunch;
                                $lastPunchInBatchTimestamp = $prevTimestamp;
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignorar si no se puede parsear
                    }
                }
            }

            // Determinar el último fichaje relevante (el más reciente entre BD y lote)
            $relevantLastPunch = null;
            $relevantLastTimestamp = null;
            
            if ($lastPunchInDb) {
                $relevantLastPunch = $lastPunchInDb;
                $relevantLastTimestamp = $lastPunchInDb->timestamp;
            }
            
            if ($lastPunchInBatch && $lastPunchInBatchTimestamp) {
                if (!$relevantLastTimestamp || $lastPunchInBatchTimestamp->gt($relevantLastTimestamp)) {
                    // El fichaje del lote es más reciente, usar ese
                    $relevantLastPunch = (object)[
                        'event_type' => $lastPunchInBatch['event_type'],
                        'timestamp' => $lastPunchInBatchTimestamp,
                    ];
                    $relevantLastTimestamp = $lastPunchInBatchTimestamp;
                }
            }

            // Validar secuencia lógica con el último fichaje relevante
            if ($relevantLastPunch) {
                if ($relevantLastPunch->event_type === $punch['event_type']) {
                    if ($punch['event_type'] === PunchEvent::TYPE_IN) {
                        $errors[] = 'No se puede registrar una entrada sin una salida previa.';
                    } else {
                        $errors[] = 'No se puede registrar una salida sin una entrada previa.';
                    }
                } else {
                    // Validar coherencia temporal: OUT debe ser posterior al IN correspondiente
                    if ($punch['event_type'] === PunchEvent::TYPE_OUT && $relevantLastPunch->event_type === PunchEvent::TYPE_IN) {
                        if ($timestamp->lte($relevantLastTimestamp)) {
                            $errors[] = 'La salida debe ser posterior a la entrada correspondiente.';
                        }
                    }
                }
            }

            // Verificar duplicados con fichajes existentes
            $duplicate = PunchEvent::where('employee_id', $punch['employee_id'])
                ->where('event_type', $punch['event_type'])
                ->where('timestamp', $timestamp->format('Y-m-d H:i:s'))
                ->exists();

            if ($duplicate) {
                $errors[] = 'Ya existe un fichaje con los mismos datos (empleado, tipo y fecha/hora).';
            }

            // Validar contra otros fichajes del mismo lote
            foreach ($validPunches as $prevValid) {
                $prevPunch = $prevValid['punch'];
                if ($prevPunch['employee_id'] === $punch['employee_id']) {
                    try {
                        $prevTimestamp = Carbon::parse($prevPunch['timestamp']);
                        // Si el fichaje previo es del mismo tipo y es más reciente que el último relevante
                        if ($prevPunch['event_type'] === $punch['event_type'] && 
                            $prevTimestamp->gt($timestamp)) {
                            // Hay un fichaje del mismo tipo más adelante en el tiempo
                            $errors[] = 'Hay un fichaje del mismo tipo con fecha posterior en el mismo lote.';
                        }
                        // Validar que OUT sea posterior a IN en el mismo lote
                        if ($punch['event_type'] === PunchEvent::TYPE_OUT && $prevPunch['event_type'] === PunchEvent::TYPE_IN) {
                            if ($timestamp->lte($prevTimestamp)) {
                                $errors[] = 'La salida debe ser posterior a la entrada en el mismo lote.';
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignorar
                    }
                }
            }

            if (empty($errors)) {
                $validPunches[] = [
                    'index' => $index,
                    'punch' => $punch,
                    'timestamp' => $timestamp,
                ];
            } else {
                $invalidPunches[] = [
                    'index' => $index,
                    'punch' => $punch,
                    'errors' => $errors,
                ];
            }
        }

        // Si todos tienen errores, retornar error
        if (empty($validPunches)) {
            return response()->json([
                'message' => 'Error al crear fichajes masivos',
                'userMessage' => 'Todos los fichajes tienen errores de validación.',
                'errors' => array_map(function ($invalid) {
                    return [
                        'index' => $invalid['index'],
                        'message' => implode(' ', $invalid['errors']),
                    ];
                }, $invalidPunches),
            ], 422);
        }

        // Crear fichajes válidos en transacción
        $results = [];
        $created = 0;
        $failed = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            // Ordenar por timestamp para mantener coherencia
            usort($validPunches, function ($a, $b) {
                return $a['timestamp']->lte($b['timestamp']) ? -1 : 1;
            });

            foreach ($validPunches as $validPunch) {
                $punch = $validPunch['punch'];
                $timestamp = $validPunch['timestamp'];
                $deviceId = $punch['device_id'] ?? 'manual-admin';

                try {
                    // Verificar duplicados nuevamente (por si otro proceso creó fichajes)
                    $duplicate = PunchEvent::where('employee_id', $punch['employee_id'])
                        ->where('event_type', $punch['event_type'])
                        ->where('timestamp', $timestamp->format('Y-m-d H:i:s'))
                        ->exists();

                    if ($duplicate) {
                        throw new \Exception('Ya existe un fichaje con los mismos datos.');
                    }

                    // Validar secuencia nuevamente (por si otro proceso creó fichajes)
                    // Obtener el último fichaje existente que sea ANTERIOR al timestamp actual
                    $lastPunchInDb = PunchEvent::where('employee_id', $punch['employee_id'])
                        ->where('timestamp', '<', $timestamp)
                        ->orderBy('timestamp', 'desc')
                        ->first();

                    // Buscar el último fichaje del mismo lote ya creado que sea anterior
                    $lastPunchInBatch = null;
                    $lastPunchInBatchTimestamp = null;
                    foreach ($results as $prevResult) {
                        if ($prevResult['success'] && isset($prevResult['punch'])) {
                            $prevPunch = $prevResult['punch'];
                            if ($prevPunch['employee_id'] === $punch['employee_id']) {
                                try {
                                    $prevTimestamp = Carbon::parse($prevPunch['timestamp']);
                                    if ($prevTimestamp->lt($timestamp)) {
                                        if (!$lastPunchInBatchTimestamp || $prevTimestamp->gt($lastPunchInBatchTimestamp)) {
                                            $lastPunchInBatch = $prevPunch;
                                            $lastPunchInBatchTimestamp = $prevTimestamp;
                                        }
                                    }
                                } catch (\Exception $e) {
                                    // Ignorar
                                }
                            }
                        }
                    }

                    // Determinar el último fichaje relevante
                    $relevantLastPunch = null;
                    if ($lastPunchInDb) {
                        $relevantLastPunch = $lastPunchInDb;
                    }
                    if ($lastPunchInBatch && $lastPunchInBatchTimestamp) {
                        if (!$relevantLastPunch || $lastPunchInBatchTimestamp->gt($relevantLastPunch->timestamp)) {
                            $relevantLastPunch = (object)[
                                'event_type' => $lastPunchInBatch['event_type'],
                                'timestamp' => $lastPunchInBatchTimestamp,
                            ];
                        }
                    }

                    if ($relevantLastPunch && $relevantLastPunch->event_type === $punch['event_type']) {
                        throw new \Exception('No se puede registrar fichajes consecutivos del mismo tipo.');
                    }

                    $punchEvent = PunchEvent::create([
                        'employee_id' => $punch['employee_id'],
                        'event_type' => $punch['event_type'],
                        'device_id' => $deviceId,
                        'timestamp' => $timestamp,
                    ]);

                    $results[] = [
                        'index' => $validPunch['index'],
                        'success' => true,
                        'punch' => [
                            'id' => $punchEvent->id,
                            'employee_id' => $punchEvent->employee_id,
                            'event_type' => $punchEvent->event_type,
                            'timestamp' => $punchEvent->timestamp->toIso8601String(),
                            'device_id' => $punchEvent->device_id,
                            'created_at' => $punchEvent->created_at->toIso8601String(),
                        ],
                    ];
                    $created++;

                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $validPunch['index'],
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                    $errors[] = [
                        'index' => $validPunch['index'],
                        'message' => $e->getMessage(),
                    ];
                    $failed++;
                }
            }

            // Si hay errores pero algunos se crearon, decidir si hacer rollback
            // Opción A: Rollback completo si hay algún error
            if ($failed > 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Error al crear fichajes masivos',
                    'userMessage' => 'Algunos fichajes no pudieron crearse. Ningún fichaje fue registrado.',
                    'data' => [
                        'created' => 0,
                        'failed' => $failed + count($invalidPunches),
                        'results' => $results,
                        'errors' => array_merge(
                            $errors,
                            array_map(function ($invalid) {
                                return [
                                    'index' => $invalid['index'],
                                    'message' => implode(' ', $invalid['errors']),
                                ];
                            }, $invalidPunches)
                        ),
                    ],
                ], 200);
            }

            DB::commit();

            // Agregar resultados de fichajes inválidos
            foreach ($invalidPunches as $invalid) {
                $results[] = [
                    'index' => $invalid['index'],
                    'success' => false,
                    'error' => implode(' ', $invalid['errors']),
                ];
            }

            // Ordenar resultados por índice original
            usort($results, function ($a, $b) {
                return $a['index'] <=> $b['index'];
            });

            return response()->json([
                'data' => [
                    'created' => $created,
                    'failed' => $failed + count($invalidPunches),
                    'results' => $results,
                    'errors' => array_map(function ($invalid) {
                        return [
                            'index' => $invalid['index'],
                            'message' => implode(' ', $invalid['errors']),
                        ];
                    }, $invalidPunches),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al crear fichajes masivos',
                'userMessage' => 'Ocurrió un error inesperado al registrar los fichajes.',
                'errors' => [],
            ], 500);
        }
    }

    /**
     * Validar la secuencia lógica de un fichaje.
     * 
     * @param \App\Models\Employee $employee
     * @param string $eventType
     * @param \Carbon\Carbon $timestamp
     * @return array
     */
    private function validatePunchSequence(Employee $employee, string $eventType, Carbon $timestamp): array
    {
        // Obtener el último fichaje existente que sea ANTERIOR al timestamp actual
        $lastPunch = PunchEvent::where('employee_id', $employee->id)
            ->where('timestamp', '<', $timestamp)
            ->orderBy('timestamp', 'desc')
            ->first();

        if (!$lastPunch) {
            // No hay fichajes previos anteriores a este timestamp, el fichaje puede ser IN o OUT
            return ['valid' => true, 'userMessage' => '', 'errors' => []];
        }

        // Validar que no sean dos fichajes consecutivos del mismo tipo
        if ($lastPunch->event_type === $eventType) {
            if ($eventType === PunchEvent::TYPE_IN) {
                return [
                    'valid' => false,
                    'userMessage' => 'No se puede registrar una entrada sin una salida previa',
                    'errors' => [
                        'event_type' => ['El último fichaje del empleado es una entrada, no se puede registrar otra entrada'],
                    ],
                ];
            } else {
                return [
                    'valid' => false,
                    'userMessage' => 'No se puede registrar una salida sin una entrada previa',
                    'errors' => [
                        'event_type' => ['El último fichaje del empleado es una salida, no se puede registrar otra salida'],
                    ],
                ];
            }
        }

        // Validar coherencia temporal: OUT debe ser posterior al IN
        if ($eventType === PunchEvent::TYPE_OUT && $lastPunch->event_type === PunchEvent::TYPE_IN) {
            if ($timestamp->lte($lastPunch->timestamp)) {
                return [
                    'valid' => false,
                    'userMessage' => 'La salida debe ser posterior a la entrada correspondiente',
                    'errors' => [
                        'timestamp' => ['La salida debe ser posterior a la entrada'],
                    ],
                ];
            }
        }

        return ['valid' => true, 'userMessage' => '', 'errors' => []];
    }
}

