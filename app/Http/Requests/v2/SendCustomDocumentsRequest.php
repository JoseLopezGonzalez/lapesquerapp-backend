<?php

namespace App\Http\Requests\v2;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class SendCustomDocumentsRequest extends FormRequest
{
    protected static array $validDocumentTypes = [
        'loading-note',
        'packing-list',
        'CMR',
        'valued-loading-note',
        'order-confirmation',
        'transport-pickup-request',
    ];

    protected static array $validRecipientKeys = [
        'customer',
        'transport',
        'salesperson',
    ];

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
        return [
            'documents' => 'required|array|min:1',
            'documents.*.type' => 'required|string|in:' . implode(',', self::$validDocumentTypes),
            'documents.*.recipients' => 'required|array|min:1',
            'documents.*.recipients.*' => 'string|in:' . implode(',', self::$validRecipientKeys),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documents.required' => 'Debe indicar al menos un documento a enviar.',
            'documents.min' => 'Debe indicar al menos un documento a enviar.',
            'documents.*.type.required' => 'Cada documento debe tener un tipo.',
            'documents.*.type.in' => 'El tipo de documento no es válido. Valores permitidos: ' . implode(', ', self::$validDocumentTypes),
            'documents.*.recipients.required' => 'Cada documento debe tener al menos un destinatario.',
            'documents.*.recipients.min' => 'Cada documento debe tener al menos un destinatario.',
            'documents.*.recipients.*.in' => 'El destinatario no es válido. Valores permitidos: ' . implode(', ', self::$validRecipientKeys),
        ];
    }
}
