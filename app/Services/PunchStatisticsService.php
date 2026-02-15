<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PunchEvent;
use Carbon\Carbon;

/**
 * Servicio de estadísticas de fichajes por período.
 */
class PunchStatisticsService
{
    /**
     * Obtener datos de estadísticas para un período. Requiere date_start <= date_end.
     *
     * @return array Estructura 'data' del JSON de estadísticas.
     */
    public function getData(Carbon $dateStart, Carbon $dateEnd): array
    {
        $dateStart = $dateStart->copy()->startOfDay();
        $dateEnd = $dateEnd->copy()->endOfDay();

        $periodLabel = $this->buildPeriodLabel($dateStart, $dateEnd);
        $period = $this->buildPeriodType($dateStart, $dateEnd);
        $daysDiff = $dateStart->diffInDays($dateEnd);
        $previousDateEnd = $dateStart->copy()->subDay()->endOfDay();
        $previousDateStart = $previousDateEnd->copy()->subDays($daysDiff)->startOfDay();

        $allEmployees = Employee::all();
        $employeeIds = $allEmployees->pluck('id');

        if ($employeeIds->isEmpty()) {
            return $this->getEmptyResponse($periodLabel, $dateStart, $dateEnd, $period);
        }

        $periodEvents = PunchEvent::whereBetween('timestamp', [$dateStart, $dateEnd])
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('timestamp', 'asc')
            ->get()
            ->groupBy('employee_id');

        $previousPeriodEvents = PunchEvent::whereBetween('timestamp', [$previousDateStart, $previousDateEnd])
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('timestamp', 'asc')
            ->get()
            ->groupBy('employee_id');

        $employeeStats = [];
        $totalHours = 0;
        $totalDaysWithActivity = [];
        $totalSessions = 0;
        $closedSessionsCount = 0;
        $allDayHours = [];
        $incidentsDetails = [];
        $anomalousDaysDetails = [];
        $dayActivityDetails = [];

        foreach ($allEmployees as $employee) {
            $employeeEvents = $periodEvents->get($employee->id, collect())->sortBy('timestamp')->values();
            if ($employeeEvents->isEmpty()) {
                continue;
            }

            $eventsByDay = $employeeEvents->groupBy(fn ($event) => $event->timestamp->format('Y-m-d'));

            $employeeTotalMinutes = 0;
            $employeeDaysWithActivity = 0;
            $employeeSessions = 0;
            $employeeClosedSessions = 0;

            foreach ($eventsByDay as $day => $dayEvents) {
                $dayEvents = $dayEvents->sortBy('timestamp')->values();
                $dayMinutes = 0;
                $daySessions = 0;
                $dayClosedSessions = 0;
                $hasOpenSession = false;
                $openSessionEntry = null;
                $hasOrphanExit = false;
                $orphanExit = null;

                if ($dayEvents->isNotEmpty() && $dayEvents->first()->event_type === PunchEvent::TYPE_OUT) {
                    $hasOrphanExit = true;
                    $orphanExit = $dayEvents->first();
                }

                for ($i = 0; $i < $dayEvents->count(); $i++) {
                    if ($dayEvents[$i]->event_type === PunchEvent::TYPE_IN) {
                        $nextOut = $dayEvents->slice($i + 1)->firstWhere('event_type', PunchEvent::TYPE_OUT);
                        if ($nextOut) {
                            $sessionMinutes = $dayEvents[$i]->timestamp->diffInMinutes($nextOut->timestamp);
                            $dayMinutes += $sessionMinutes;
                            $daySessions++;
                            $dayClosedSessions++;
                            $closedSessionsCount++;
                        } else {
                            $hasOpenSession = true;
                            $openSessionEntry = $dayEvents[$i];
                        }
                    } elseif ($dayEvents[$i]->event_type === PunchEvent::TYPE_OUT) {
                        $prevIn = $dayEvents->slice(0, $i)->reverse()->firstWhere('event_type', PunchEvent::TYPE_IN);
                        if (!$prevIn) {
                            $hasOrphanExit = true;
                            $orphanExit = $dayEvents[$i];
                        }
                    }
                }

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

                if ($dayMinutes > 0) {
                    $dayHours = round($dayMinutes / 60, 2);
                    $employeeTotalMinutes += $dayMinutes;
                    $employeeDaysWithActivity++;
                    $totalDaysWithActivity[$day] = true;
                    $employeeSessions += $daySessions;
                    $employeeClosedSessions += $dayClosedSessions;
                    $allDayHours[] = [
                        'hours' => $dayHours,
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'date' => $day,
                    ];

                    if (!isset($dayActivityDetails[$day])) {
                        $dayActivityDetails[$day] = [
                            'date' => $day,
                            'total_hours' => 0,
                            'employees' => [],
                        ];
                    }
                    $dayActivityDetails[$day]['total_hours'] += $dayHours;
                    $dayActivityDetails[$day]['employees'][] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'hours' => $dayHours,
                    ];
                }
            }

            if ($employeeTotalMinutes > 0) {
                $employeeHoursValue = round($employeeTotalMinutes / 60, 2);
                $totalHours += $employeeHoursValue;
                $totalSessions += $employeeSessions;
                $employeeStats[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'total_hours' => $employeeHoursValue,
                    'total_minutes' => $employeeTotalMinutes,
                    'days_with_activity' => $employeeDaysWithActivity,
                    'sessions' => $employeeClosedSessions,
                ];
            }
        }

        $anomalousDaysCount = 0;
        if (!empty($allDayHours)) {
            $dayHoursValues = array_column($allDayHours, 'hours');
            $averageHoursPerDay = array_sum($dayHoursValues) / count($dayHoursValues);
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

        $previousTotalHours = 0;
        foreach ($previousPeriodEvents as $events) {
            $eventsByDay = $events->groupBy(fn ($event) => $event->timestamp->format('Y-m-d'));
            foreach ($eventsByDay as $dayEvents) {
                $dayEvents = collect($dayEvents)->sortBy('timestamp')->values();
                for ($i = 0; $i < $dayEvents->count(); $i++) {
                    if ($dayEvents[$i]->event_type === PunchEvent::TYPE_IN) {
                        $nextOut = $dayEvents->slice($i + 1)->firstWhere('event_type', PunchEvent::TYPE_OUT);
                        if ($nextOut) {
                            $previousTotalHours += round($dayEvents[$i]->timestamp->diffInMinutes($nextOut->timestamp) / 60, 2);
                        }
                    }
                }
            }
        }

        $activeEmployeesCount = count($employeeStats);
        $averageHoursPerEmployee = $activeEmployeesCount > 0 ? round($totalHours / $activeEmployeesCount, 2) : 0;
        $daysWithActivityCount = count($totalDaysWithActivity);
        $averageHoursPerDay = $daysWithActivityCount > 0 ? round($totalHours / $daysWithActivityCount, 2) : 0;

        $averageHoursPerDayPerEmployee = 0;
        if (!empty($employeeStats)) {
            $hoursPerDayByEmployee = [];
            foreach ($employeeStats as $emp) {
                if ($emp['days_with_activity'] > 0) {
                    $hoursPerDayByEmployee[] = $emp['total_hours'] / $emp['days_with_activity'];
                }
            }
            if (!empty($hoursPerDayByEmployee)) {
                $averageHoursPerDayPerEmployee = round(array_sum($hoursPerDayByEmployee) / count($hoursPerDayByEmployee), 2);
            }
        }

        $hoursVariation = 0;
        $hoursVariationPercentage = 0;
        if ($previousTotalHours > 0) {
            $hoursVariation = round($totalHours - $previousTotalHours, 2);
            $hoursVariationPercentage = round(($hoursVariation / $previousTotalHours) * 100, 2);
        } elseif ($totalHours > 0) {
            $hoursVariation = $totalHours;
            $hoursVariationPercentage = 100;
        }

        $topEmployees = [];
        $bottomEmployees = [];
        if (!empty($employeeStats)) {
            usort($employeeStats, fn ($a, $b) => $b['total_hours'] <=> $a['total_hours']);
            $totalEmployeesCount = count($employeeStats);
            if ($totalEmployeesCount < 6) {
                $topCount = (int) ceil($totalEmployeesCount / 2);
                $bottomCount = $totalEmployeesCount - $topCount;
                $topEmployees = array_slice($employeeStats, 0, $topCount);
                $bottomEmployees = array_reverse(array_slice($employeeStats, -$bottomCount));
            } else {
                $topEmployees = array_slice($employeeStats, 0, 3);
                $topEmployeeIds = array_column($topEmployees, 'employee_id');
                $bottomCandidates = array_reverse($employeeStats);
                $bottomEmployees = [];
                foreach ($bottomCandidates as $emp) {
                    if (!in_array($emp['employee_id'], $topEmployeeIds)) {
                        $bottomEmployees[] = $emp;
                        if (count($bottomEmployees) >= 3) {
                            break;
                        }
                    }
                }
            }
        }

        $mostActiveDays = [];
        $leastActiveDays = [];
        if (!empty($dayActivityDetails)) {
            $daysArray = array_values($dayActivityDetails);
            foreach ($daysArray as &$day) {
                $day['employees_count'] = count($day['employees']);
                $day['average_hours_per_employee'] = $day['employees_count'] > 0
                    ? round($day['total_hours'] / $day['employees_count'], 2)
                    : 0;
                unset($day['total_hours']);
            }
            unset($day);
            usort($daysArray, fn ($a, $b) => $b['average_hours_per_employee'] <=> $a['average_hours_per_employee']);
            $mostActiveDays = array_slice($daysArray, 0, 3);
            $leastActiveDays = array_slice(array_reverse($daysArray), 0, 3);
        }

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
                'total_hours' => round($totalHours, 2),
                'average_hours_per_employee' => $averageHoursPerEmployee,
                'hours_variation' => $hoursVariation,
                'hours_variation_percentage' => $hoursVariationPercentage,
                'previous_period_hours' => round($previousTotalHours, 2),
                'breakdown' => [
                    'top_employees' => array_map(fn ($emp) => [
                        'employee_id' => $emp['employee_id'],
                        'employee_name' => $emp['employee_name'],
                        'total_hours' => $emp['total_hours'],
                    ], $topEmployees),
                    'bottom_employees' => array_map(fn ($emp) => [
                        'employee_id' => $emp['employee_id'],
                        'employee_name' => $emp['employee_name'],
                        'total_hours' => $emp['total_hours'],
                    ], $bottomEmployees),
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
        ];
    }

    /**
     * Respuesta vacía para un rango de fechas (sin empleados o sin datos). Calcula period label y type internamente.
     */
    public function getEmptyResponseForDates(Carbon $dateStart, Carbon $dateEnd): array
    {
        $dateStart = $dateStart->copy()->startOfDay();
        $dateEnd = $dateEnd->copy()->endOfDay();
        $periodLabel = $this->buildPeriodLabel($dateStart, $dateEnd);
        $period = $this->buildPeriodType($dateStart, $dateEnd);
        return $this->getEmptyResponse($periodLabel, $dateStart, $dateEnd, $period);
    }

    /**
     * Respuesta vacía cuando no hay empleados o no hay datos.
     */
    public function getEmptyResponse(string $periodLabel, Carbon $dateStart, Carbon $dateEnd, string $period): array
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
                'breakdown' => ['top_employees' => [], 'bottom_employees' => []],
            ],
            'activity' => [
                'days_with_activity' => 0,
                'average_hours_per_day' => 0,
                'average_hours_per_day_per_employee' => 0,
                'breakdown' => ['most_active_days' => [], 'least_active_days' => []],
            ],
            'incidents' => ['open_incidents_count' => 0, 'details' => []],
            'anomalies' => ['anomalous_days_count' => 0, 'details' => []],
            'context' => ['active_employees_count' => 0, 'total_employees_count' => 0],
        ];
    }

    private function buildPeriodLabel(Carbon $dateStart, Carbon $dateEnd): string
    {
        $daysDiff = $dateStart->diffInDays($dateEnd);
        if ($daysDiff <= 7) {
            return $dateStart->format('d/m/Y') . ' - ' . $dateEnd->format('d/m/Y');
        }
        if ($dateStart->isSameMonth($dateEnd) && $dateStart->day === 1 && $dateEnd->isLastOfMonth()) {
            $months = [
                1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
                5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
                9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
            ];
            $monthName = $months[$dateStart->month] ?? $dateStart->format('F');
            return ucfirst($monthName) . ' ' . $dateStart->year;
        }
        if ($dateStart->isSameYear($dateEnd) && $dateStart->dayOfYear === 1 && $dateEnd->isLastOfYear()) {
            return $dateStart->format('Y');
        }
        return $dateStart->format('d/m/Y') . ' - ' . $dateEnd->format('d/m/Y');
    }

    private function buildPeriodType(Carbon $dateStart, Carbon $dateEnd): string
    {
        if ($dateStart->isSameMonth($dateEnd) && $dateStart->day === 1 && $dateEnd->isLastOfMonth()) {
            return 'month';
        }
        if ($dateStart->isSameYear($dateEnd) && $dateStart->dayOfYear === 1 && $dateEnd->isLastOfYear()) {
            return 'year';
        }
        return 'custom';
    }
}
