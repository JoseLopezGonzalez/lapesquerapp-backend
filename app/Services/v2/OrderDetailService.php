<?php

namespace App\Services\v2;

use App\Models\Order;

class OrderDetailService
{
    /**
     * Obtiene un pedido con eager loading completo para detalle (show).
     * Evita N+1 y reduce memoria con selects explícitos.
     *
     * @return Order
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function getOrderForDetail(string $id): Order
    {
        return Order::select([
            'id', 'buyer_reference', 'customer_id', 'payment_term_id', 'billing_address', 'shipping_address',
            'transportation_notes', 'production_notes', 'accounting_notes', 'salesperson_id', 'emails',
            'transport_id', 'entry_date', 'load_date', 'status', 'order_type', 'incoterm_id', 'created_at', 'updated_at',
            'truck_plate', 'trailer_plate', 'temperature',
        ])->with([
            'customer' => fn ($q) => $q->select([
                'id', 'name', 'alias', 'vat_number', 'payment_term_id', 'billing_address', 'shipping_address',
                'transportation_notes', 'production_notes', 'accounting_notes', 'salesperson_id', 'emails',
                'contact_info', 'country_id', 'transport_id', 'a3erp_code', 'facilcom_code', 'created_at', 'updated_at',
            ]),
            'customer.payment_term' => fn ($q) => $q->select(['id', 'name', 'created_at', 'updated_at']),
            'customer.salesperson' => fn ($q) => $q->select(['id', 'name', 'emails', 'created_at', 'updated_at']),
            'customer.country' => fn ($q) => $q->select(['id', 'name', 'created_at', 'updated_at']),
            'customer.transport' => fn ($q) => $q->select(['id', 'name', 'vat_number', 'address', 'emails', 'created_at', 'updated_at']),
            'payment_term' => fn ($q) => $q->select(['id', 'name', 'created_at', 'updated_at']),
            'salesperson' => fn ($q) => $q->select(['id', 'name', 'emails', 'created_at', 'updated_at']),
            'transport' => fn ($q) => $q->select(['id', 'name', 'vat_number', 'address', 'emails', 'created_at', 'updated_at']),
            'incoterm' => fn ($q) => $q->select(['id', 'code', 'description', 'created_at', 'updated_at']),
            'plannedProductDetails' => fn ($q) => $q->select(['id', 'order_id', 'product_id', 'tax_id', 'quantity', 'boxes', 'unit_price', 'created_at', 'updated_at']),
            'plannedProductDetails.product' => fn ($q) => $q->select([
                'id', 'family_id', 'species_id', 'capture_zone_id', 'name', 'a3erp_code', 'facil_com_code',
                'article_gtin', 'box_gtin', 'pallet_gtin',
            ]),
            'plannedProductDetails.product.species' => fn ($q) => $q->select(['id', 'name', 'scientific_name', 'fao', 'image', 'fishing_gear_id']),
            'plannedProductDetails.product.species.fishingGear' => fn ($q) => $q->select(['id', 'name']),
            'plannedProductDetails.product.captureZone' => fn ($q) => $q->select(['id', 'name']),
            'plannedProductDetails.product.family' => fn ($q) => $q->select(['id', 'name', 'description', 'category_id', 'active']),
            'plannedProductDetails.product.family.category' => fn ($q) => $q->select(['id', 'name']),
            'plannedProductDetails.tax' => fn ($q) => $q->select(['id', 'name', 'rate']),
            'incident' => fn ($q) => $q->select([
                'id', 'order_id', 'description', 'status', 'resolution_type', 'resolution_notes', 'resolved_at', 'created_at', 'updated_at',
            ]),
            'pallets' => fn ($q) => $q->select(['id', 'observations', 'status', 'order_id']),
            'pallets.boxes' => fn ($q) => $q->select(['id', 'pallet_id', 'box_id', 'created_at', 'updated_at']),
            'pallets.boxes.box' => fn ($q) => $q->select(['id', 'article_id', 'lot', 'gs1_128', 'gross_weight', 'net_weight', 'created_at']),
            'pallets.boxes.box.productionInputs' => fn ($q) => $q->select(['id', 'box_id']),
            'pallets.boxes.box.product' => fn ($q) => $q->select([
                'id', 'family_id', 'species_id', 'capture_zone_id', 'name', 'a3erp_code', 'facil_com_code',
                'article_gtin', 'box_gtin', 'pallet_gtin',
            ]),
            'pallets.boxes.box.product.species' => fn ($q) => $q->select(['id', 'name', 'scientific_name', 'fao', 'image', 'fishing_gear_id']),
            'pallets.boxes.box.product.species.fishingGear' => fn ($q) => $q->select(['id', 'name']),
            'pallets.boxes.box.product.captureZone' => fn ($q) => $q->select(['id', 'name']),
            'pallets.boxes.box.product.family' => fn ($q) => $q->select(['id', 'name', 'description', 'category_id', 'active']),
            'pallets.boxes.box.product.family.category' => fn ($q) => $q->select(['id', 'name']),
        ])->findOrFail($id);
    }
}
