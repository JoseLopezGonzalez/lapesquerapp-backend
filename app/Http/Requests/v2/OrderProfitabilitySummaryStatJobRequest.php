<?php

namespace App\Http\Requests\v2;

class OrderProfitabilitySummaryStatJobRequest extends OrderProfitabilitySummaryRequest
{
    public function rules(): array
    {
        // Sin límite de rango: este endpoint dispara un job en background (sin timeout de petición HTTP),
        // a diferencia de OrderProfitabilitySummaryRequest, que sí se resuelve de forma síncrona.
        return array_merge(parent::rules(), [
            'dateTo' => 'required|date|after_or_equal:dateFrom',
        ]);
    }
}
