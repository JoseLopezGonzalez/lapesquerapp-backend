<?php

namespace App\Http\Requests\v2;

use App\Models\Production;
use Illuminate\Foundation\Http\FormRequest;

class IndexOrphanBoxesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Production::class);
    }

    public function rules(): array
    {
        return [
            'lot' => 'nullable|string|max:255',
            'article_id' => 'nullable|integer|exists:tenant.products,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:id,lot,created_at,net_weight',
            'sort_dir' => 'nullable|in:asc,desc',
        ];
    }
}
