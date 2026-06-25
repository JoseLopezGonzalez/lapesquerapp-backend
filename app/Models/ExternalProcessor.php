<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalProcessor extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'name',
        'legal_name',
        'vat_number',
        'sanitary_registration_number',
        'contact_person',
        'phone',
        'emails',
        'address',
        'city',
        'postal_code',
        'province',
        'country_id',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function getEmailsArrayAttribute(): array
    {
        return $this->extractEmails('regular');
    }

    public function getCcEmailsArrayAttribute(): array
    {
        return $this->extractEmails('cc');
    }

    protected function extractEmails(string $type): array
    {
        $emails = explode(';', (string) $this->emails);
        $result = [];

        foreach ($emails as $email) {
            $email = trim($email);

            if ($email === '') {
                continue;
            }

            $isCc = str_starts_with($email, 'CC:') || str_starts_with($email, 'cc:');

            if ($type === 'cc' && $isCc) {
                $result[] = substr($email, 3);
            } elseif ($type === 'regular' && ! $isCc) {
                $result[] = $email;
            }
        }

        return $result;
    }
}
