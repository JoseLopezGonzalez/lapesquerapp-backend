<?php

namespace App\Http\Requests\v2;

class OrderProfitabilitySummaryExportJobRequest extends OrderProfitabilitySummaryRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'onlyMissingCosts' => 'nullable|boolean',
        ]);
    }
}
