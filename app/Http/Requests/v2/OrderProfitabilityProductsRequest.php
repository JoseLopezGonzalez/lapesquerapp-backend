<?php

namespace App\Http\Requests\v2;

use App\Http\Requests\v2\Concerns\ValidatesProfitabilityDateRange;
use Illuminate\Foundation\Http\FormRequest;

class OrderProfitabilityProductsRequest extends FormRequest
{
    use ValidatesProfitabilityDateRange;

    public function authorize(): bool
    {
        return true;
    }

    protected function maxRangeDays(): int
    {
        return 60;
    }

    public function rules(): array
    {
        return [
            'dateFrom' => 'required|date',
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom', $this->maxRangeRule()],
        ];
    }
}
