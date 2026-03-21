<?php

namespace App\Http\Requests\v2;

use App\Models\RouteStop;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRouteStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:' . implode(',', RouteStop::validStatuses()),
            'result_type' => 'nullable|string|in:' . implode(',', RouteStop::validResultTypes()) . '|required_if:status,' . RouteStop::STATUS_COMPLETED,
            'result_notes' => 'nullable|string|max:1000',
        ];
    }
}
