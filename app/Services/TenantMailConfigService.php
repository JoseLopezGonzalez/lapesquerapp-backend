<?php

namespace App\Services;

use App\Exceptions\MailConfigurationException;
use Illuminate\Support\Facades\Config;

class TenantMailConfigService
{
    /**
     * Configura el mailer de Laravel con los valores del tenant.
     * Valida que la configuración esté completa antes de configurar.
     *
     * @throws MailConfigurationException Si la configuración está incompleta
     */
    public function configureTenantMailer(): void
    {
        // Obtener configuración del tenant
        $mailHost = $this->getTenantSetting('company.mail.host');
        $mailPort = $this->getTenantSetting('company.mail.port');
        $mailEncryption = $this->getTenantSetting('company.mail.encryption');
        $mailUsername = $this->getTenantSetting('company.mail.username');
        $mailPassword = $this->getTenantSetting('company.mail.password');
        $mailFromAddress = $this->getTenantSetting('company.mail.from_address');
        $mailFromName = $this->getTenantSetting('company.mail.from_name');
        $mailMailer = $this->getTenantSetting('company.mail.mailer', 'smtp');

        // Validar que los campos obligatorios estén configurados
        $missingFields = [];

        if (empty($mailHost)) {
            $missingFields[] = 'Servidor SMTP (host)';
        }

        if (empty($mailPort)) {
            $missingFields[] = 'Puerto SMTP';
        }

        if (empty($mailEncryption)) {
            $missingFields[] = 'Encriptación (TLS/SSL)';
        }

        if (empty($mailUsername)) {
            $missingFields[] = 'Usuario SMTP';
        }

        if (empty($mailPassword)) {
            $missingFields[] = 'Contraseña SMTP';
        }

        if (empty($mailFromAddress)) {
            $missingFields[] = 'Email remitente';
        }

        // Si faltan campos obligatorios, lanzar excepción
        if (!empty($missingFields)) {
            $fieldsList = implode(', ', $missingFields);
            throw new MailConfigurationException(
                "La configuración de email del tenant está incompleta. Faltan los siguientes campos: {$fieldsList}",
                500
            );
        }

        // Configurar el mailer dinámicamente
        Config::set('mail.default', $mailMailer);
        Config::set('mail.mailers.smtp.host', $mailHost);
        Config::set('mail.mailers.smtp.port', (int) $mailPort);
        Config::set('mail.mailers.smtp.encryption', $mailEncryption);
        Config::set('mail.mailers.smtp.username', $mailUsername);
        Config::set('mail.mailers.smtp.password', $mailPassword);

        // Configurar remitente
        Config::set('mail.from.address', $mailFromAddress);
        Config::set('mail.from.name', $mailFromName ?: $this->getTenantSetting('company.name', 'PesquerApp'));
    }

    /**
     * Obtiene la configuración del tenant, tratando strings vacíos como no configurado.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getTenantSetting(string $key, $default = null)
    {
        $value = tenantSetting($key, $default);

        // Si el valor es string vacío, tratarlo como no configurado
        if ($value === '' || $value === null) {
            return $default;
        }

        return $value;
    }

    /**
     * Obtiene el email remitente configurado del tenant.
     *
     * @return string
     * @throws MailConfigurationException
     */
    public function getFromAddress(): string
    {
        $fromAddress = $this->getTenantSetting('company.mail.from_address');
        
        if (empty($fromAddress)) {
            throw new MailConfigurationException('El email remitente no está configurado');
        }

        return $fromAddress;
    }

    /**
     * Obtiene el nombre remitente configurado del tenant.
     *
     * @return string
     */
    public function getFromName(): string
    {
        $fromName = $this->getTenantSetting('company.mail.from_name');
        
        if (empty($fromName)) {
            // Fallback al nombre de la empresa
            return $this->getTenantSetting('company.name', 'PesquerApp');
        }

        return $fromName;
    }
}

