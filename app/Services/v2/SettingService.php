<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;

class SettingService
{
    /**
     * Keys permitidos para rol comercial (solo lectura; excluye company.mail.* y otros sensibles).
     */
    private const COMERCIAL_SETTINGS_WHITELIST = [
        'company.name',
        'company.logo_url',
        'company.frontend_url',
    ];

    /**
     * Devuelve todos los settings key=>value del tenant.
     * Ofusca company.mail.password (devuelve ******** si existe).
     * Si el usuario es comercial, devuelve solo la whitelist (keys permitidos).
     *
     * @param User|null $user Usuario actual (para restringir a whitelist si es comercial)
     */
    public function getAllKeyValue(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $all = Setting::getAllKeyValue();

        if ($user && $user->hasRole(Role::Comercial->value)) {
            $all = array_intersect_key($all, array_flip(self::COMERCIAL_SETTINGS_WHITELIST));
        }

        if (array_key_exists(Setting::SENSITIVE_KEY_PASSWORD, $all)) {
            $all[Setting::SENSITIVE_KEY_PASSWORD] = '********';
        }

        return $all;
    }

    /**
     * Actualiza settings desde un array clave-valor.
     * Si no viene company.mail.password y se están actualizando otros campos de email
     * y ya existe configuración de email, el password no se toca (se mantiene el actual).
     */
    public function updateFromPayload(array $data): void
    {
        if (!array_key_exists(Setting::SENSITIVE_KEY_PASSWORD, $data)) {
            $isUpdatingEmailSettings = false;
            foreach (array_keys($data) as $key) {
                if (str_starts_with((string) $key, 'company.mail.')) {
                    $isUpdatingEmailSettings = true;
                    break;
                }
            }

            if ($isUpdatingEmailSettings) {
                $hasExistingEmailConfig = Setting::query()
                    ->whereIn('key', [
                        'company.mail.host',
                        'company.mail.username',
                        'company.mail.from_address',
                    ])
                    ->whereNotNull('value')
                    ->where('value', '!=', '')
                    ->exists();

                if ($hasExistingEmailConfig) {
                    // No añadir company.mail.password al payload; no se actualizará en el bucle
                }
            }
        }

        foreach ($data as $key => $value) {
            Setting::query()->updateOrInsert(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
