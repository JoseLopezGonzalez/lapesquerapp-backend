<?php

namespace App\Http\Requests\v2;

use App\Models\Production;
use Illuminate\Foundation\Http\FormRequest;

class IndexOrphanStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Production::class);
    }

    public function rules(): array
    {
        return [
            'lot'      => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_dir' => 'nullable|in:asc,desc',
        ];
    }
}
