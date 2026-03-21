<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldOperator extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'name',
        'emails',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function routeTemplates()
    {
        return $this->hasMany(RouteTemplate::class);
    }

    public function routes()
    {
        return $this->hasMany(DeliveryRoute::class, 'field_operator_id');
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

            if ($type === 'cc' && (str_starts_with($email, 'CC:') || str_starts_with($email, 'cc:'))) {
                $result[] = substr($email, 3);
            } elseif ($type === 'regular' && ! str_starts_with($email, 'CC:') && ! str_starts_with($email, 'cc:')) {
                $result[] = $email;
            }
        }

        return $result;
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'emails' => $this->emailsArray,
            'ccEmails' => $this->ccEmailsArray,
            'userId' => $this->user_id,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
