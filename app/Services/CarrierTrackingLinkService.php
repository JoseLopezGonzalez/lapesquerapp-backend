<?php

namespace App\Services;

/**
 * Resuelve enlaces públicos de seguimiento por naviera a partir del número de contenedor.
 * No hay API de tracking en vivo integrada (ver docs/pedidos/31-guia-frontend-naviera-y-seguimiento.md):
 * esto solo genera el enlace a la página pública de seguimiento de cada naviera.
 */
class CarrierTrackingLinkService
{
    private const URL_TEMPLATES = [
        'maersk' => 'https://www.maersk.com/tracking/%s',
        'msc' => 'https://www.msc.com/en/track-a-shipment?agencyPath=msc&trackingNumber=%s',
    ];

    private const LABELS = [
        'maersk' => 'Maersk',
        'msc' => 'MSC',
        'other' => 'Otra naviera',
    ];

    /**
     * Devuelve la URL pública de seguimiento, o null si la naviera no tiene plantilla
     * conocida (p. ej. "other") o falta alguno de los datos necesarios.
     */
    public function urlFor(?string $shippingLine, ?string $containerNumber): ?string
    {
        if (empty($shippingLine) || empty($containerNumber) || ! isset(self::URL_TEMPLATES[$shippingLine])) {
            return null;
        }

        return sprintf(self::URL_TEMPLATES[$shippingLine], rawurlencode($containerNumber));
    }

    public function label(?string $shippingLine): ?string
    {
        return self::LABELS[$shippingLine] ?? null;
    }
}
