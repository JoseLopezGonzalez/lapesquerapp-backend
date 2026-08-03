<?php

namespace App\Http\Requests\v2\Concerns;

use Carbon\Carbon;
use Closure;

trait ValidatesProfitabilityDateRange
{
    protected function maxRangeDays(): int
    {
        return 186;
    }

    protected function maxRangeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $from = $this->input('dateFrom');
            if (! $from || ! $value) {
                return;
            }

            $days = Carbon::parse($from)->diffInDays(Carbon::parse($value));
            $max = $this->maxRangeDays();

            if ($days > $max) {
                $fail(sprintf(
                    'El rango de fechas no puede superar %d días. Para periodos más amplios, usa la consulta asíncrona.',
                    $max
                ));
            }
        };
    }
}
