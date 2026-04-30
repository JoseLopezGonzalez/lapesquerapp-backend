<?php

namespace App\Http\Requests\v2;

use App\Models\Production;
use Illuminate\Foundation\Http\FormRequest;

class IndexProductionControlPanelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Production::class);
    }

    public function rules(): array
    {
        return [
            'lot'                   => 'nullable|string|max:255',
            'species_id'            => 'nullable|exists:tenant.species,id',
            'status'                => 'nullable|in:open,closed',
            'date_from'             => 'nullable|date',
            'date_to'               => 'nullable|date|after_or_equal:date_from',
            'reconciliation_status' => 'nullable|in:ok,warning,error',
            'per_page'              => 'nullable|integer|min:1|max:50',
            'sort_by'               => 'nullable|in:date,lot,id',
            'sort_dir'              => 'nullable|in:asc,desc',
        ];
    }
}
