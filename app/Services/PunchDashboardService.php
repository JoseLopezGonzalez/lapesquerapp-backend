<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PunchEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Servicio de datos del dashboard de fichajes (trabajadores activos, estadísticas del día, incidencias).
 */
class PunchDashboardService
{
    /**
     * Obtener datos completos del dashboard para una fecha.
     *
     * @param \Carbon\Carbon|null $date Fecha del día (por defecto hoy)
     * @return array{message: string, data: array}
     */
    public function getData(?Carbon $date = null): array
    {
        $date = $date ? $date->copy()->startOfDay() : now()->startOfDay();
        $dateEnd = $date->copy()->endOfDay();

        $allEmployees = Employee::all();
        $todayEvents = PunchEvent::whereBetween('timestamp', [$date, $dateEnd])
            ->with('employee')
            ->orderBy('timestamp', 'asc')
            ->get()
            ->groupBy('employee_id');

        $employeeIds = $allEmployees->pluck('id');
        $lastEventsByEmployee = $this->getLastEventsByEmployee($employeeIds);

        $allEmployeesData = [];
        $workingEmployees = [];
        $totalEntriesToday = 0;
        $totalExitsToday = 0;
        $entriesByDevice = [];

        foreach ($allEmployees as $employee) {
            $lastEventData = $lastEventsByEmployee->get($employee->id);
            $employeeTodayEvents = $todayEvents->get($employee->id, collect())->sortBy('timestamp')->values();

            $todayEntries = $employeeTodayEvents->where('event_type', PunchEvent::TYPE_IN);
            $todayExits = $employeeTodayEvents->where('event_type', PunchEvent::TYPE_OUT);
            $todayEntriesCount = $todayEntries->count();
            $todayExitsCount = $todayExits->count();
            $totalEntriesToday += $todayEntriesCount;
            $totalExitsToday += $todayExitsCount;

            foreach ($todayEntries as $entry) {
                $deviceId = $entry->device_id;
                $entriesByDevice[$deviceId] = ($entriesByDevice[$deviceId] ?? 0) + 1;
            }

            $status = 'no_ha_fichado';
            if ($lastEventData) {
                $lastEventIsToday = $lastEventData->timestamp->isSameDay($date);
                if ($lastEventData->event_type === PunchEvent::TYPE_IN) {
                    $status = $lastEventIsToday ? 'trabajando' : 'ha_finalizado';
                } else {
                    if ($lastEventIsToday) {
                        $hoursSinceLastExit = now()->diffInHours($lastEventData->timestamp);
                        $status = $hoursSinceLastExit < 2 ? 'descansando' : 'ha_finalizado';
                    } else {
                        $status = 'ha_finalizado';
                    }
                }
            }

            $isWorking = $status === 'trabajando';
            $firstEntry = $todayEntries->first();
            $firstEntryTimestamp = $firstEntry ? $firstEntry->timestamp : null;
            $lastExit = $todayExits->last();
            $lastExitTimestamp = $lastExit ? $lastExit->timestamp : null;

            $todayTotalMinutes = 0;
            $completeSessionsCount = 0;
            $breakTimeMinutes = 0;
            $events = $employeeTodayEvents;
            for ($i = 0; $i < $events->count(); $i++) {
                if ($events[$i]->event_type === PunchEvent::TYPE_IN) {
                    $nextOut = $events->slice($i + 1)->firstWhere('event_type', PunchEvent::TYPE_OUT);
                    if ($nextOut) {
                        $sessionMinutes = $events[$i]->timestamp->diffInMinutes($nextOut->timestamp);
                        $todayTotalMinutes += $sessionMinutes;
                        $completeSessionsCount++;
                    } else {
                        $sessionMinutes = $events[$i]->timestamp->diffInMinutes(now());
                        $todayTotalMinutes += $sessionMinutes;
                    }
                } elseif ($events[$i]->event_type === PunchEvent::TYPE_OUT && $i > 0) {
                    $nextIn = $events->slice($i + 1)->firstWhere('event_type', PunchEvent::TYPE_IN);
                    if ($nextIn) {
                        $breakTimeMinutes += $events[$i]->timestamp->diffInMinutes($nextIn->timestamp);
                    }
                }
            }

            $todayTotalHours = round($todayTotalMinutes / 60, 2);
            $breakTimeHours = round($breakTimeMinutes / 60, 2);
            $todayTotalHoursFormatted = $this->formatTime($todayTotalMinutes);
            $minutesSinceLastAction = $lastEventData ? now()->diffInMinutes($lastEventData->timestamp) : null;

            $currentSessionMinutes = null;
            $currentSessionFormatted = null;
            $currentEntryTimestamp = null;
            $currentEntryDeviceId = null;
            if ($isWorking && $lastEventData) {
                $currentSessionMinutes = now()->diffInMinutes($lastEventData->timestamp);
                $currentSessionFormatted = $this->formatTime($currentSessionMinutes);
                $currentEntryTimestamp = $lastEventData->timestamp->toIso8601String();
                $currentEntryDeviceId = $lastEventData->device_id;
            }

            $employeeData = [
                'id' => $employee->id,
                'name' => $employee->name,
                'nfcUid' => $employee->nfc_uid,
                'status' => $status,
                'isWorking' => $isWorking,
                'firstEntryTimestamp' => $firstEntryTimestamp ? $firstEntryTimestamp->toIso8601String() : null,
                'lastExitTimestamp' => $lastExitTimestamp ? $lastExitTimestamp->toIso8601String() : null,
                'todayTotalHours' => $todayTotalHours,
                'todayTotalMinutes' => $todayTotalMinutes,
                'todayTotalHoursFormatted' => $todayTotalHoursFormatted,
                'completeSessionsCount' => $completeSessionsCount,
                'breakTimeMinutes' => $breakTimeMinutes,
                'breakTimeHours' => $breakTimeHours,
                'breakTimeFormatted' => $this->formatTime($breakTimeMinutes),
                'todayEntriesCount' => $todayEntriesCount,
                'todayExitsCount' => $todayExitsCount,
                'minutesSinceLastAction' => $minutesSinceLastAction,
                'timeSinceLastActionFormatted' => $minutesSinceLastAction ? $this->formatTime($minutesSinceLastAction) : null,
                'currentSessionMinutes' => $currentSessionMinutes,
                'currentSessionHours' => $currentSessionMinutes ? round($currentSessionMinutes / 60, 2) : null,
                'currentSessionFormatted' => $currentSessionFormatted,
                'currentEntryTimestamp' => $currentEntryTimestamp,
                'currentEntryDeviceId' => $currentEntryDeviceId,
            ];

            $allEmployeesData[] = $employeeData;
            if ($isWorking) {
                $workingEmployees[] = $employeeData;
            }
        }

        usort($workingEmployees, fn ($a, $b) => ($b['currentSessionMinutes'] ?? 0) <=> ($a['currentSessionMinutes'] ?? 0));
        usort($allEmployeesData, fn ($a, $b) => $b['todayTotalMinutes'] <=> $a['todayTotalMinutes']);

        $recentTimeLimit = now()->subMinutes(30);
        $recentPunches = PunchEvent::whereBetween('timestamp', [$date, $dateEnd])
            ->where('timestamp', '>=', $recentTimeLimit)
            ->with('employee')
            ->orderBy('timestamp', 'desc')
            ->take(20)
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'employeeName' => $event->employee->name,
                'eventType' => $event->event_type,
                'timestamp' => $event->timestamp->toIso8601String(),
                'deviceId' => $event->device_id,
            ])
            ->values();

        $missingPunches = $this->getMissingPunches($date);

        $statistics = [
            'totalWorking' => count($workingEmployees),
            'totalEmployees' => $allEmployees->count(),
            'totalEntriesToday' => $totalEntriesToday,
            'totalExitsToday' => $totalExitsToday,
            'entriesByDevice' => $entriesByDevice,
        ];

        return [
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
        ];
    }

    /**
     * Último evento de fichaje por empleado (solo Eloquent, sin DB::connection).
     *
     * @param \Illuminate\Support\Collection<int, int> $employeeIds
     * @return \Illuminate\Support\Collection<int, \App\Models\PunchEvent>
     */
    private function getLastEventsByEmployee(Collection $employeeIds): Collection
    {
        if ($employeeIds->isEmpty()) {
            return collect();
        }

        $sub = PunchEvent::query()
            ->selectRaw('employee_id, MAX(timestamp) as max_ts')
            ->whereIn('employee_id', $employeeIds)
            ->groupBy('employee_id');

        return PunchEvent::query()
            ->whereIn('punch_events.employee_id', $employeeIds)
            ->joinSub($sub, 'latest', function ($join) {
                $join->on('punch_events.employee_id', '=', 'latest.employee_id')
                    ->on('punch_events.timestamp', '=', 'latest.max_ts');
            })
            ->select('punch_events.*')
            ->get()
            ->keyBy('employee_id');
    }

    /**
     * Detectar faltas de fichaje (entradas sin salida, salidas sin entrada) en los últimos 30 días.
     *
     * @return array<string, array>
     */
    private function getMissingPunches(Carbon $date): array
    {
        $missingPunches = [];
        $yesterday = now()->subDay()->startOfDay();
        $limit = $yesterday->copy()->subDays(30);

        $pastInEvents = PunchEvent::where('event_type', PunchEvent::TYPE_IN)
            ->where('timestamp', '<', $date)
            ->where('timestamp', '>=', $limit)
            ->with('employee')
            ->orderBy('timestamp', 'desc')
            ->get()
            ->groupBy('employee_id');

        foreach ($pastInEvents as $employeeId => $inEvents) {
            $employee = $inEvents->first()->employee;
            foreach ($inEvents as $inEvent) {
                $eventDate = $inEvent->timestamp->copy()->startOfDay();
                $eventDateEnd = $eventDate->copy()->endOfDay();
                $hasOut = PunchEvent::where('employee_id', $employeeId)
                    ->where('event_type', PunchEvent::TYPE_OUT)
                    ->where('timestamp', '>', $inEvent->timestamp)
                    ->whereBetween('timestamp', [$eventDate, $eventDateEnd])
                    ->exists();
                if (!$hasOut) {
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
                            'entryTimestamp' => $inEvent->timestamp->toIso8601String(),
                            'entryDeviceId' => $inEvent->device_id,
                            'hoursOpen' => round(now()->diffInHours($inEvent->timestamp), 1),
                        ];
                    }
                }
            }
        }

        $pastOutEvents = PunchEvent::where('event_type', PunchEvent::TYPE_OUT)
            ->where('timestamp', '<', $date)
            ->where('timestamp', '>=', $limit)
            ->with('employee')
            ->orderBy('timestamp', 'desc')
            ->get()
            ->groupBy('employee_id');

        foreach ($pastOutEvents as $employeeId => $outEvents) {
            $employee = $outEvents->first()->employee;
            foreach ($outEvents as $outEvent) {
                $eventDate = $outEvent->timestamp->copy()->startOfDay();
                $eventDateEnd = $eventDate->copy()->endOfDay();
                $hasIn = PunchEvent::where('employee_id', $employeeId)
                    ->where('event_type', PunchEvent::TYPE_IN)
                    ->where('timestamp', '<', $outEvent->timestamp)
                    ->whereBetween('timestamp', [$eventDate, $eventDateEnd])
                    ->exists();
                if (!$hasIn) {
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
                            'exitTimestamp' => $outEvent->timestamp->toIso8601String(),
                            'exitDeviceId' => $outEvent->device_id,
                        ];
                    }
                }
            }
        }

        usort($missingPunches, fn ($a, $b) => $a['daysAgo'] <=> $b['daysAgo']);

        return $missingPunches;
    }

    private function formatTime(int $minutes): string
    {
        if ($minutes === 0) {
            return '0m';
        }
        $hours = (int) floor($minutes / 60);
        $mins = $minutes % 60;
        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}m";
        }
        if ($hours > 0) {
            return "{$hours}h";
        }
        return "{$mins}m";
    }
}
