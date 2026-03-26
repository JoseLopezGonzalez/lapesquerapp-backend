<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\CancelCrmAgendaActionRequest;
use App\Http\Requests\v2\IndexCrmAgendaRequest;
use App\Http\Requests\v2\IndexCrmAgendaSummaryRequest;
use App\Http\Requests\v2\ResolveNextActionRequest;
use App\Http\Requests\v2\RescheduleCrmAgendaActionRequest;
use App\Http\Requests\v2\ShowCrmAgendaPendingRequest;
use App\Http\Requests\v2\StoreCrmAgendaActionRequest;
use App\Services\v2\CrmAgendaService;
use Illuminate\Http\JsonResponse;

class CrmAgendaController extends Controller
{
    public function calendar(IndexCrmAgendaRequest $request): JsonResponse
    {
        $v = $request->validated();

        $events = CrmAgendaService::listCalendar(
            $request->user(),
            $v['startDate'] ?? null,
            $v['endDate'] ?? null,
            $v['targetType'] ?? null,
            $v['status'] ?? null,
        );

        return response()->json([
            'data' => [
                'events' => $events,
            ],
        ]);
    }

    public function summary(IndexCrmAgendaSummaryRequest $request): JsonResponse
    {
        $v = $request->validated();

        $summary = CrmAgendaService::summary(
            $request->user(),
            $v['limitNext'] ?? 10,
        );

        return response()->json([
            'data' => $summary,
        ]);
    }

    public function store(StoreCrmAgendaActionRequest $request): JsonResponse
    {
        $v = $request->validated();

        $action = CrmAgendaService::createPendingFromInteraction(
            $request->user(),
            $v['targetType'],
            (int) $v['targetId'],
            $v['nextActionAt'],
            $v['nextActionNote'] ?? null,
            $v['sourceInteractionId'] ?? null,
            $v['previousActionId'] ?? null,
        );

        return response()->json([
            'message' => 'Acción de agenda registrada correctamente.',
            'data' => $action->toArrayAssoc(),
        ], 201);
    }

    public function reschedule(RescheduleCrmAgendaActionRequest $request, string $id): JsonResponse
    {
        $v = $request->validated();

        $action = CrmAgendaService::reschedule(
            $request->user(),
            (int) $id,
            $v['nextActionAt'],
            $v['nextActionNote'] ?? null,
            $v['sourceInteractionId'] ?? null,
        );

        return response()->json([
            'message' => 'Acción reprogramada correctamente.',
            'data' => $action->toArrayAssoc(),
        ]);
    }

    public function cancel(CancelCrmAgendaActionRequest $request, string $id): JsonResponse
    {
        $v = $request->validated();

        $action = CrmAgendaService::cancel(
            $request->user(),
            (int) $id,
            $v['reason'],
        );

        return response()->json([
            'message' => 'Acción cancelada correctamente.',
            'data' => $action->toArrayAssoc(),
        ]);
    }

    public function resolveNextAction(ResolveNextActionRequest $request): JsonResponse
    {
        $v = $request->validated();

        $result = CrmAgendaService::resolveNextAction(
            $request->user(),
            $v['targetType'],
            (int) $v['targetId'],
            $v['strategy'],
            $v['nextActionAt'] ?? null,
            $v['description'] ?? null,
            $v['reason'] ?? null,
            isset($v['sourceInteractionId']) ? (int) $v['sourceInteractionId'] : null,
            isset($v['expectedPendingId']) ? (int) $v['expectedPendingId'] : null
        );

        return response()->json([
            'message' => 'Próxima acción actualizada correctamente.',
            'data' => $result,
        ]);
    }

    public function pending(ShowCrmAgendaPendingRequest $request): JsonResponse
    {
        $v = $request->validated();

        $snapshot = CrmAgendaService::pendingSnapshot(
            $request->user(),
            $v['targetType'],
            (int) $v['targetId']
        );

        return response()->json([
            'data' => $snapshot,
        ]);
    }
}

