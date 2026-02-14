<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class OrderRankingStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'groupBy' => 'required|in:client,country,product',
            'valueType' => 'required|in:totalAmount,totalQuantity',
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date',
            'speciesId' => 'nullable|integer|exists:tenant.species,id',
        ];
    }
}
