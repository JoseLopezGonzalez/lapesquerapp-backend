<?php

namespace App\Http\Requests\v2;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMaritimeExportDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = Order::findOrFail($this->route('orderId'));

        return $this->user()->can('view', $order);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $orderId = $this->route('orderId');

        return [
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'attachmentIds' => 'nullable|array',
            'attachmentIds.*' => [
                'integer',
                Rule::exists('tenant.attachments', 'id')
                    ->where(fn ($query) => $query
                        ->where('attachable_type', 'order')
                        ->where('attachable_id', $orderId)
                        ->whereNull('deleted_at')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'El asunto del mensaje es obligatorio.',
            'body.required' => 'El cuerpo del mensaje es obligatorio.',
            'attachmentIds.*.exists' => 'Uno de los adjuntos seleccionados no existe o no pertenece a este pedido.',
        ];
    }
}
