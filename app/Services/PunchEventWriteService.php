<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PunchEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de escritura para eventos de fichaje (NFC, manual, bulk).
 */
class PunchEventWriteService
{
    /**
     * Crear fichaje desde NFC (uid o employee_id + device_id). Timestamp = now().
     * Retorna array con punchEvent y employee o null si empleado no encontrado.
     */
    public function storeFromNfc(array $validated): array
    {
        $employee = isset($validated['employee_id'])
            ? Employee::find($validated['employee_id'])
            : Employee::where('nfc_uid', $validated['uid'])->first();

        if (!$employee) {
            return ['success' => false, 'error' => 'EMPLOYEE_NOT_FOUND'];
        }

        $timestamp = now('UTC');
        $eventType = $this->determineEventType($employee, $timestamp);

        DB::beginTransaction();
        try {
            $punchEvent = PunchEvent::create([
                'employee_id' => $employee->id,
                'event_type' => $eventType,
                'device_id' => $validated['device_id'],
                'timestamp' => $timestamp,
            ]);
            DB::commit();
            return [
                'success' => true,
                'punchEvent' => $punchEvent,
                'employee' => $employee,
                'event_type' => $eventType,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            return ['success' => false, 'error' => 'PUNCH_REGISTRATION_FAILED'];
        }
    }

    /**
     * Crear fichaje manual. Valida secuencia y duplicados.
     * Retorna ['success' => true, 'punchEvent' => ...] o ['success' => false, 'userMessage' => ..., 'errors' => ...].
     */
    public function storeManual(array $validated): array
    {
        $deviceId = $validated['device_id'] ?? 'manual-admin';

        try {
            $timestamp = Carbon::parse($validated['timestamp'])->utc();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'userMessage' => 'El formato de fecha es inválido. Use formato ISO 8601 (ejemplo: 2024-01-15T08:30:00.000Z).',
                'errors' => ['timestamp' => ['El formato de fecha es inválido.']],
            ];
        }

        if ($timestamp->isFuture()) {
            return [
                'success' => false,
                'userMessage' => 'No se pueden registrar fichajes con fechas futuras.',
                'errors' => ['timestamp' => ['No se permiten fechas futuras.']],
            ];
        }

        $employee = Employee::find($validated['employee_id']);
        if (!$employee) {
            return [
                'success' => false,
                'userMessage' => 'El empleado especificado no existe.',
                'errors' => ['employee_id' => ['El empleado especificado no existe.']],
            ];
        }

        $validationResult = $this->validatePunchSequence($employee, $validated['event_type'], $timestamp);
        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'userMessage' => $validationResult['userMessage'],
                'errors' => $validationResult['errors'],
            ];
        }

        $duplicate = PunchEvent::where('employee_id', $employee->id)
            ->where('event_type', $validated['event_type'])
            ->where('timestamp', $timestamp->copy()->format('Y-m-d H:i:s'))
            ->first();

        if ($duplicate) {
            return [
                'success' => false,
                'userMessage' => 'Ya existe un fichaje con los mismos datos (empleado, tipo y fecha/hora).',
                'errors' => ['timestamp' => ['Ya existe un fichaje duplicado.']],
            ];
        }

        try {
            DB::beginTransaction();
            $punchEvent = PunchEvent::create([
                'employee_id' => $employee->id,
                'event_type' => $validated['event_type'],
                'device_id' => $deviceId,
                'timestamp' => $timestamp,
            ]);
            DB::commit();
            return ['success' => true, 'punchEvent' => $punchEvent];
        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'userMessage' => 'Ocurrió un error inesperado al registrar el fichaje.',
                'errors' => [],
            ];
        }
    }

    /**
     * Validar lote de fichajes. Retorna ['valid' => int, 'invalid' => int, 'validation_results' => array].
     */
    public function bulkValidate(array $validated): array
    {
        $validationResults = [];
        $validCount = 0;
        $invalidCount = 0;
        $employeeIds = collect($validated['punches'])->pluck('employee_id')->unique();
        $employees = Employee::whereIn('id', $employeeIds)->get()->keyBy('id');

        foreach ($validated['punches'] as $index => $punch) {
            $errors = $this->validateSinglePunchForBulk($punch, $index, $validated['punches'], $validationResults, $employees);
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

        return [
            'valid' => $validCount,
            'invalid' => $invalidCount,
            'validation_results' => $validationResults,
        ];
    }

    /**
     * Crear lote de fichajes. Valida igual que bulkValidate y luego crea los válidos en transacción.
     * Retorna array para respuesta JSON (data creado/failed/results/errors) o error 422/500.
     */
    public function bulkStore(array $validated): array
    {
        $validPunches = [];
        $invalidPunches = [];
        $employeeIds = collect($validated['punches'])->pluck('employee_id')->unique();
        $employees = Employee::whereIn('id', $employeeIds)->get()->keyBy('id');
        $validationResults = [];

        foreach ($validated['punches'] as $index => $punch) {
            $errors = $this->validateSinglePunchForBulk($punch, $index, $validated['punches'], $validationResults, $employees);
            try {
                $timestamp = Carbon::parse($punch['timestamp'])->utc();
            } catch (\Exception $e) {
                $timestamp = null;
            }
            if (empty($errors) && $timestamp) {
                $validPunches[] = ['index' => $index, 'punch' => $punch, 'timestamp' => $timestamp];
            } else {
                $invalidPunches[] = ['index' => $index, 'punch' => $punch, 'errors' => $errors];
            }
            $validationResults[] = ['index' => $index, 'valid' => empty($errors), 'errors' => $errors];
        }

        if (empty($validPunches)) {
            return [
                'success' => false,
                'allInvalid' => true,
                'invalidPunches' => $invalidPunches,
            ];
        }

        usort($validPunches, fn ($a, $b) => $a['timestamp']->lte($b['timestamp']) ? -1 : 1);

        $results = [];
        $created = 0;
        $failed = 0;
        $errors = [];

        try {
            DB::beginTransaction();
            foreach ($validPunches as $validPunch) {
                $punch = $validPunch['punch'];
                $timestamp = $validPunch['timestamp'];
                $deviceId = $punch['device_id'] ?? 'manual-admin';

                try {
                    if (PunchEvent::where('employee_id', $punch['employee_id'])
                        ->where('event_type', $punch['event_type'])
                        ->where('timestamp', $timestamp->format('Y-m-d H:i:s'))
                        ->exists()) {
                        throw new \Exception('Ya existe un fichaje con los mismos datos.');
                    }

                    $startOfDay = $this->startOfDayFor($timestamp);
                    $lastPunchInDb = PunchEvent::where('employee_id', $punch['employee_id'])
                        ->where('timestamp', '>=', $startOfDay)
                        ->where('timestamp', '<', $timestamp)
                        ->orderBy('timestamp', 'desc')
                        ->first();

                    $lastPunchInBatch = null;
                    $lastPunchInBatchTimestamp = null;
                    foreach ($results as $prevResult) {
                        if (($prevResult['success'] ?? false) && isset($prevResult['punch']['employee_id']) && $prevResult['punch']['employee_id'] === $punch['employee_id']) {
                            try {
                                $prevTimestamp = Carbon::parse($prevResult['punch']['timestamp'])->utc();
                                $sameDay = $this->startOfDayFor($prevTimestamp)->eq($startOfDay);
                                if ($sameDay && $prevTimestamp->lt($timestamp) && (!$lastPunchInBatchTimestamp || $prevTimestamp->gt($lastPunchInBatchTimestamp))) {
                                    $lastPunchInBatch = $prevResult['punch'];
                                    $lastPunchInBatchTimestamp = $prevTimestamp;
                                }
                            } catch (\Exception $e) {
                            }
                        }
                    }

                    $relevantLastPunch = $lastPunchInDb;
                    if ($lastPunchInBatch && $lastPunchInBatchTimestamp && (!$relevantLastPunch || $lastPunchInBatchTimestamp->gt($relevantLastPunch->timestamp))) {
                        $relevantLastPunch = (object)['event_type' => $lastPunchInBatch['event_type'], 'timestamp' => $lastPunchInBatchTimestamp];
                    }

                    if (!$relevantLastPunch && ($punch['event_type'] ?? null) === PunchEvent::TYPE_OUT) {
                        throw new \Exception('El primer fichaje del día debe ser una entrada.');
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
                } catch (\Throwable $e) {
                    $results[] = ['index' => $validPunch['index'], 'success' => false, 'error' => $e->getMessage()];
                    $errors[] = ['index' => $validPunch['index'], 'message' => $e->getMessage()];
                    $failed++;
                }
            }

            if ($failed > 0) {
                DB::rollBack();
                return [
                    'success' => false,
                    'rollback' => true,
                    'created' => 0,
                    'failed' => $failed + count($invalidPunches),
                    'results' => $results,
                    'errors' => array_merge($errors, array_map(fn ($i) => ['index' => $i['index'], 'message' => implode(' ', $i['errors'])], $invalidPunches)),
                ];
            }

            DB::commit();
            foreach ($invalidPunches as $invalid) {
                $results[] = ['index' => $invalid['index'], 'success' => false, 'error' => implode(' ', $invalid['errors'])];
            }
            usort($results, fn ($a, $b) => $a['index'] <=> $b['index']);

            return [
                'success' => true,
                'created' => $created,
                'failed' => count($invalidPunches),
                'results' => $results,
                'errors' => array_map(fn ($i) => ['index' => $i['index'], 'message' => implode(' ', $i['errors'])], $invalidPunches),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'exception' => true,
                'userMessage' => 'Ocurrió un error inesperado al registrar los fichajes.',
            ];
        }
    }

    /**
     * Inferir tipo de evento (IN/OUT) para fichajes sin tipo (p. ej. NFC).
     * Solo se considera la secuencia del mismo día natural: el primer fichaje del día es siempre entrada.
     */
    private function determineEventType(Employee $employee, Carbon $timestamp): string
    {
        $startOfDay = $this->startOfDayFor($timestamp);
        $endOfDay = $this->endOfDayFor($timestamp);

        $lastEventSameDay = PunchEvent::where('employee_id', $employee->id)
            ->where('timestamp', '>=', $startOfDay)
            ->where('timestamp', '<=', $endOfDay)
            ->orderBy('timestamp', 'desc')
            ->first();

        if (!$lastEventSameDay || $lastEventSameDay->event_type === PunchEvent::TYPE_OUT) {
            return PunchEvent::TYPE_IN;
        }
        return PunchEvent::TYPE_OUT;
    }

    private function startOfDayFor(Carbon $timestamp): Carbon
    {
        $tz = config('app.business_timezone', 'Europe/Madrid');

        return $timestamp->copy()->timezone($tz)->startOfDay()->utc();
    }

    private function endOfDayFor(Carbon $timestamp): Carbon
    {
        $tz = config('app.business_timezone', 'Europe/Madrid');

        return $timestamp->copy()->timezone($tz)->endOfDay()->utc();
    }

    /**
     * Validar secuencia entrada/salida. Solo se considera el mismo día natural:
     * el primer fichaje del día debe ser entrada.
     */
    private function validatePunchSequence(Employee $employee, string $eventType, Carbon $timestamp): array
    {
        $startOfDay = $this->startOfDayFor($timestamp);

        $lastPunchSameDay = PunchEvent::where('employee_id', $employee->id)
            ->where('timestamp', '>=', $startOfDay)
            ->where('timestamp', '<', $timestamp)
            ->orderBy('timestamp', 'desc')
            ->first();

        if (!$lastPunchSameDay) {
            // Primer fichaje del día: solo se permite entrada
            if ($eventType === PunchEvent::TYPE_OUT) {
                return [
                    'valid' => false,
                    'userMessage' => 'No se puede registrar una salida sin una entrada previa',
                    'errors' => ['event_type' => ['El primer fichaje del día debe ser una entrada']],
                ];
            }
            return ['valid' => true, 'userMessage' => '', 'errors' => []];
        }

        if ($lastPunchSameDay->event_type === $eventType) {
            if ($eventType === PunchEvent::TYPE_IN) {
                return [
                    'valid' => false,
                    'userMessage' => 'No se puede registrar una entrada sin una salida previa',
                    'errors' => ['event_type' => ['El último fichaje del empleado hoy es una entrada, no se puede registrar otra entrada']],
                ];
            }
            return [
                'valid' => false,
                'userMessage' => 'No se puede registrar una salida sin una entrada previa',
                'errors' => ['event_type' => ['El último fichaje del empleado hoy es una salida, no se puede registrar otra salida']],
            ];
        }

        if ($eventType === PunchEvent::TYPE_OUT && $lastPunchSameDay->event_type === PunchEvent::TYPE_IN && $timestamp->lte($lastPunchSameDay->timestamp)) {
            return [
                'valid' => false,
                'userMessage' => 'La salida debe ser posterior a la entrada correspondiente',
                'errors' => ['timestamp' => ['La salida debe ser posterior a la entrada']],
            ];
        }

        return ['valid' => true, 'userMessage' => '', 'errors' => []];
    }

    /**
     * @param array $punch
     * @param int $index
     * @param array $allPunches
     * @param array $validationResults [['index'=>int,'valid'=>bool], ...]
     * @param \Illuminate\Support\Collection $employees
     * @return array List of error strings
     */
    private function validateSinglePunchForBulk(array $punch, int $index, array $allPunches, array $validationResults, $employees): array
    {
        $errors = [];
        try {
            $timestamp = Carbon::parse($punch['timestamp'])->utc();
        } catch (\Exception $e) {
            $errors[] = 'El formato de fecha es inválido. Use formato ISO 8601.';
            return $errors;
        }

        if ($timestamp->isFuture()) {
            $errors[] = 'No se permiten fechas futuras.';
        }

        $employee = $employees->get($punch['employee_id']);
        if (!$employee) {
            $errors[] = 'El empleado especificado no existe.';
            return $errors;
        }

        $startOfDay = $this->startOfDayFor($timestamp);
        $lastPunchInDb = PunchEvent::where('employee_id', $punch['employee_id'])
            ->where('timestamp', '>=', $startOfDay)
            ->where('timestamp', '<', $timestamp)
            ->orderBy('timestamp', 'desc')
            ->first();

        $lastPunchInBatch = null;
        $lastPunchInBatchTimestamp = null;
        foreach ($validationResults as $prevResult) {
            $prevIndex = $prevResult['index'] ?? null;
            $prevValid = $prevResult['valid'] ?? false;
            if ($prevIndex !== null && $prevIndex < $index && $prevValid) {
                $prevPunch = $allPunches[$prevIndex] ?? null;
                if ($prevPunch && ($prevPunch['employee_id'] ?? null) === $punch['employee_id']) {
                    try {
                        $prevTimestamp = Carbon::parse($prevPunch['timestamp'])->utc();
                        $sameDay = $this->startOfDayFor($prevTimestamp)->eq($startOfDay);
                        if ($sameDay && $prevTimestamp->lt($timestamp) && (!$lastPunchInBatchTimestamp || $prevTimestamp->gt($lastPunchInBatchTimestamp))) {
                            $lastPunchInBatch = $prevPunch;
                            $lastPunchInBatchTimestamp = $prevTimestamp;
                        }
                    } catch (\Exception $e) {
                    }
                }
            }
        }

        $relevantLastPunch = null;
        $relevantLastTimestamp = null;
        if ($lastPunchInDb) {
            $relevantLastPunch = $lastPunchInDb;
            $relevantLastTimestamp = $lastPunchInDb->timestamp;
        }
        if ($lastPunchInBatch && $lastPunchInBatchTimestamp) {
            if (!$relevantLastTimestamp || $lastPunchInBatchTimestamp->gt($relevantLastTimestamp)) {
                $relevantLastPunch = (object)['event_type' => $lastPunchInBatch['event_type'], 'timestamp' => $lastPunchInBatchTimestamp];
                $relevantLastTimestamp = $lastPunchInBatchTimestamp;
            }
        }

        if (!$relevantLastPunch) {
            // Primer fichaje del día: solo se permite entrada
            if (($punch['event_type'] ?? null) === PunchEvent::TYPE_OUT) {
                $errors[] = 'El primer fichaje del día debe ser una entrada.';
            }
        } else {
            if ($relevantLastPunch->event_type === $punch['event_type']) {
                if ($punch['event_type'] === PunchEvent::TYPE_IN) {
                    $errors[] = 'No se puede registrar una entrada sin una salida previa.';
                } else {
                    $errors[] = 'No se puede registrar una salida sin una entrada previa.';
                }
            } elseif ($punch['event_type'] === PunchEvent::TYPE_OUT && $relevantLastPunch->event_type === PunchEvent::TYPE_IN && $timestamp->lte($relevantLastTimestamp)) {
                $errors[] = 'La salida debe ser posterior a la entrada correspondiente.';
            }
        }

        if (PunchEvent::where('employee_id', $punch['employee_id'])
            ->where('event_type', $punch['event_type'])
            ->where('timestamp', $timestamp->format('Y-m-d H:i:s'))
            ->exists()) {
            $errors[] = 'Ya existe un fichaje con los mismos datos (empleado, tipo y fecha/hora).';
        }

        foreach ($validationResults as $prevResult) {
            $prevIndex = $prevResult['index'] ?? null;
            $prevValid = $prevResult['valid'] ?? false;
            if ($prevIndex === null || $prevIndex >= $index || !$prevValid) {
                continue;
            }
            $prevPunch = $allPunches[$prevIndex] ?? null;
            if (!$prevPunch || ($prevPunch['employee_id'] ?? null) !== $punch['employee_id']) {
                continue;
            }
            try {
                $prevTimestamp = Carbon::parse($prevPunch['timestamp'])->utc();
                if (($prevPunch['event_type'] ?? null) === $punch['event_type'] && $prevTimestamp->gt($timestamp)) {
                    $errors[] = 'Hay un fichaje del mismo tipo con fecha posterior en el mismo lote.';
                }
                if (($punch['event_type'] ?? null) === PunchEvent::TYPE_OUT && ($prevPunch['event_type'] ?? null) === PunchEvent::TYPE_IN && $timestamp->lte($prevTimestamp)) {
                    $errors[] = 'La salida debe ser posterior a la entrada en el mismo lote.';
                }
            } catch (\Exception $e) {
            }
        }

        return $errors;
    }
}
