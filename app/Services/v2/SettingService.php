<?php

namespace App\Services\v2;

use App\Models\Setting;

class SettingService
{
    /**
     * Devuelve todos los settings key=>value del tenant.
     * Ofusca company.mail.password (devuelve ******** si existe).
     */
    public function getAllKeyValue(): array
    {
        $all = Setting::getAllKeyValue();

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
