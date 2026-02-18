<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\v2\SettingService;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(
        private SettingService $settingService
    ) {}

    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return response()->json($this->settingService->getAllKeyValue($request->user()));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        $this->settingService->updateFromPayload($request->all());

        return response()->json(['message' => 'Settings updated']);
    }
}
