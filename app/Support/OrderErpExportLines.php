<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderAuxiliaryLine;

/**
 * Filas de líneas auxiliares para exports contables A3ERP / Facilcom.
 *
 * El código de artículo ERP se toma de auxiliary_products.reference (catálogo)
 * o queda vacío / '-' en líneas ad-hoc sin producto de catálogo.
 */
class OrderErpExportLines
{
    public static function loadRelations(Order $order): void
    {
        $order->loadMissing([
            'auxiliaryLines.auxiliaryProduct',
            'auxiliaryLines.tax',
        ]);
    }

    /**
     * Filas A3ERP (formato asociativo) para líneas auxiliares de un pedido.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function a3ErpRowsForOrder(
        Order $order,
        string $serie,
        bool $useFacilcomClientCode = false,
        bool $useMissingPlaceholder = false
    ): array {
        self::loadRelations($order);

        $empty = $useMissingPlaceholder ? '-' : '';
        $rows = [];

        foreach ($order->auxiliaryLines as $line) {
            $rows[] = [
                'CABSERIE' => $serie,
                'CABNUMDOC' => $useMissingPlaceholder ? ($order->id ?: '-') : $order->id,
                'CABFECHA' => $order->load_date
                    ? date('d/m/Y', strtotime($order->load_date))
                    : ($useMissingPlaceholder ? '-' : ''),
                'CABCODCLI' => self::clientCode($order, $useFacilcomClientCode, $useMissingPlaceholder),
                'CABREFERENCIA' => $useMissingPlaceholder ? ($order->id ?: '-') : $order->id,
                'LINCODART' => self::articleCode($line, $empty),
                'LINDESCLIN' => $line->effective_description,
                'LINBULTOS' => $useMissingPlaceholder ? '-' : 0,
                'LINUNIDADES' => $line->quantity,
                'LINPRCMONEDA' => $line->unit_price,
                'LINTIPIVA' => ($line->tax?->name) ?: $empty,
            ];
        }

        return $rows;
    }

    /**
     * Filas Facilcom (array indexado) para líneas auxiliares de un pedido.
     *
     * @return array<int, array<int, mixed>>
     */
    public static function facilcomArrayRowsForOrder(Order $order, int $rowIndex): array
    {
        self::loadRelations($order);

        $rows = [];

        foreach ($order->auxiliaryLines as $line) {
            $rows[] = [
                $rowIndex,
                $order->load_date ? date('d/m/Y', strtotime($order->load_date)) : '-',
                ($order->customer['facilcom_code'] ?? null) ?: '-',
                ($order->customer['name'] ?? null) ?: '-',
                self::articleCode($line, '-'),
                $line->effective_description,
                $line->quantity,
                $line->unit_price,
                $order->load_date ? date('dmY', strtotime($order->load_date)) : '-',
            ];
        }

        return $rows;
    }

    private static function clientCode(Order $order, bool $useFacilcomClientCode, bool $useMissingPlaceholder): mixed
    {
        if ($useFacilcomClientCode) {
            $code = $order->customer?->facilcom_code;

            return ($code !== null && $code !== '') ? $code : ($useMissingPlaceholder ? '-' : '');
        }

        if ($useMissingPlaceholder) {
            $code = $order->customer?->a3erp_code;

            return ($code !== null && $code !== '') ? $code : '-';
        }

        return $order->customer?->a3erp_code ?? '';
    }

    private static function articleCode(OrderAuxiliaryLine $line, string $emptyValue): string
    {
        $code = $line->auxiliaryProduct?->reference;

        if ($code !== null && $code !== '') {
            return $code;
        }

        return $emptyValue;
    }
}
