<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Customer extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'name',
        'vat_number',
        'payment_term_id',
        'billing_address',
        'shipping_address',
        'transportation_notes',
        'production_notes',
        'accounting_notes',
        'salesperson_id',
        'emails',
        'contact_info',
        'country_id',
        'transport_id',
        'a3erp_code',
        'facilcom_code',
        'alias',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function salesperson()
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function transport()
    {
        return $this->belongsTo(Transport::class);
    }

    public function payment_term()
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'alias' => $this->alias,
            'vatNumber' => $this->vat_number,
            'paymentTerm' => $this->payment_term ? $this->payment_term->toArrayAssoc() : null,
            'billingAddress' => $this->billing_address,
            'shippingAddress' => $this->shipping_address,
            'transportationNotes' => $this->transportation_notes,
            'productionNotes' => $this->production_notes,
            'accountingNotes' => $this->accounting_notes,
            'salesperson' => $this->salesperson ? $this->salesperson->toArrayAssoc() : null,
            'emails' => $this->emailsArray,
            'ccEmails' => $this->ccEmailsArray,
            'contactInfo' => $this->contact_info,
            'country' => $this->country ? $this->country->toArrayAssoc() : null,
            'transport' => $this->transport ? $this->transport->toArrayAssoc() : null,
            'a3erpCode' => $this->a3erp_code,
            'facilcomCode' => $this->facilcom_code,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    public function toArrayAssocShort()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'alias' => $this->alias,
            'vatNumber' => $this->vat_number,
            'billingAddress' => $this->billing_address,
        ];
    }

    /**
     * Get the array of regular emails.
     *
     * @return array
     */
    public function getEmailsArrayAttribute()
    {
        return $this->extractEmails('regular');
    }


    /**
     * Get the array of CC emails.
     *
     * @return array
     */
    public function getCcEmailsArrayAttribute()
    {
        return $this->extractEmails('cc');
    }

    /**
     * Helper method to extract emails based on type.
     *
     * @param string $type 'regular' or 'cc'
     * @return array
     */
    protected function extractEmails($type)
    {
        $emails = explode(';', $this->emails);
        $result = [];

        foreach ($emails as $email) {
            $email = trim($email);
            if (empty($email)) {
                continue;
            }

            if ($type == 'cc' && (str_starts_with($email, 'CC:') || str_starts_with($email, 'cc:'))) {
                $result[] = substr($email, 3);  // Remove 'CC:' prefix and add to results 
            } elseif ($type == 'regular' && !str_starts_with($email, 'CC:') && !str_starts_with($email, 'cc:')) {
                $result[] = $email;  // Add regular email to results
            }
        }

        return $result;
    }

    /**
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($customer) {
            // Validar name no vacío
            if (empty($customer->name)) {
                throw ValidationException::withMessages([
                    'name' => 'El nombre del cliente no puede estar vacío.',
                ]);
            }

            // Validar name único por tenant
            $existing = self::where('name', $customer->name)
                ->where('id', '!=', $customer->id ?? 0)
                ->first();
            
            if ($existing) {
                throw ValidationException::withMessages([
                    'name' => 'Ya existe un cliente con este nombre.',
                ]);
            }
        });
    }

}
