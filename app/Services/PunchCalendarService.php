<?php

namespace App\Services;

use App\Models\PunchEvent;
use Carbon\Carbon;

/**
 * Servicio de datos de calendario de fichajes (fichajes por día, incidencias, anomalías).
 */
class PunchCalendarService
{
    /**
     * Obtener datos del calendario para un año y mes.
     *
     * @return array{year: int, month: int, punches_by_day: array, total_punches: int, total_employees: int}
     */
    public function getData(int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $punches = PunchEvent::whereBetween('timestamp', [$startDate, $endDate])
            ->with('employee')
            ->orderBy('timestamp', 'asc')
            ->get();

        $punchesByDay = [];
        $allDayHours = [];

        $punchesByEmployee = $punches->groupBy('employee_id');

        foreach ($punchesByEmployee as $employeeId => $employeePunches) {
            $eventsByDay = $employeePunches->groupBy(fn ($event) => $event->timestamp->format('Y-m-d'));

            foreach ($eventsByDay as $dayKey => $dayEvents) {
                $day = Carbon::parse($dayKey)->day;
                $dayEvents = $dayEvents->sortBy('timestamp')->values();

                if (!isset($punchesByDay[$day])) {
                    $punchesByDay[$day] = [
                        'punches' => [],
                        'incidents' => [],
                        'anomalies' => [],
                    ];
                }

                foreach ($dayEvents as $punch) {
                    $punchesByDay[$day]['punches'][] = [
                        'id' => $punch->id,
                        'employee_id' => $punch->employee_id,
                        'employee_name' => $punch->employee->name ?? '',
                        'event_type' => $punch->event_type,
                        'timestamp' => $punch->timestamp->toIso8601String(),
                        'device_id' => $punch->device_id,
                    ];
                }

                $dayMinutes = 0;
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
                            $dayMinutes += $dayEvents[$i]->timestamp->diffInMinutes($nextOut->timestamp);
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
                    $punchesByDay[$day]['incidents'][] = [
                        'employee_id' => $openSessionEntry->employee_id,
                        'employee_name' => $openSessionEntry->employee->name ?? '',
                        'type' => 'entry_without_exit',
                        'type_label' => 'Entrada sin salida',
                        'entry_timestamp' => $openSessionEntry->timestamp->toIso8601String(),
                        'entry_time' => $openSessionEntry->timestamp->format('H:i:s'),
                        'device_id' => $openSessionEntry->device_id,
                    ];
                }

                if ($hasOrphanExit && $orphanExit) {
                    $punchesByDay[$day]['incidents'][] = [
                        'employee_id' => $orphanExit->employee_id,
                        'employee_name' => $orphanExit->employee->name ?? '',
                        'type' => 'exit_without_entry',
                        'type_label' => 'Salida sin entrada',
                        'exit_timestamp' => $orphanExit->timestamp->toIso8601String(),
                        'exit_time' => $orphanExit->timestamp->format('H:i:s'),
                        'device_id' => $orphanExit->device_id,
                    ];
                }

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

        $minThreshold = 0;
        $maxThreshold = 0;

        if (!empty($allDayHours)) {
            $dayHoursValues = array_column($allDayHours, 'hours');
            $averageHoursPerDay = array_sum($dayHoursValues) / count($dayHoursValues);
            $minThreshold = $averageHoursPerDay * 0.5;
            $maxThreshold = $averageHoursPerDay * 2.0;

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

        foreach ($punchesByDay as $day => $dayData) {
            if (empty($dayData['punches']) && empty($dayData['incidents']) && empty($dayData['anomalies'])) {
                unset($punchesByDay[$day]);
            }
        }

        return [
            'year' => (int) $year,
            'month' => (int) $month,
            'punches_by_day' => $punchesByDay,
            'total_punches' => $punches->count(),
            'total_employees' => $punches->pluck('employee_id')->unique()->count(),
        ];
    }
}
