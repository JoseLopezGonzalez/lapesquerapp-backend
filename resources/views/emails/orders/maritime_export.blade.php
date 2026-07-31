<x-mail::message>

# {{ $order->customer->name }}

<br>

**ES -** Su pedido n.º **{{ substr($order->formattedId, 1) }}** ha sido cargado y expedido. Adjuntamos la documentación disponible en este momento. Cualquier documentación adicional que esté disponible posteriormente le será remitida por este mismo medio.



**EN -** Your order No. **{{ substr($order->formattedId, 1) }}** has been loaded and shipped. Please find the documentation currently available attached. Any additional documentation that becomes available will be sent to you through this same channel.

<br>

## Detalles del Pedido:
- Cliente: {{ $order->customer->name }}
- Número de Pedido: {{ $order->formattedId }}
@if ($order->buyer_reference)
- Buyer Reference: {{ $order->buyer_reference }}
@endif
@if ($order->maritimeShippingDetail?->vessel_name)
- Buque: {{ $order->maritimeShippingDetail->vessel_name }}
@endif
@if ($order->maritimeShippingDetail?->voyage_number)
- Nº de Viaje: {{ $order->maritimeShippingDetail->voyage_number }}
@endif
@if ($order->maritimeShippingDetail?->booking_number)
- Booking: {{ $order->maritimeShippingDetail->booking_number }}
@endif
@if ($order->maritimeShippingDetail?->loading_port)
- Puerto de Carga: {{ $order->maritimeShippingDetail->loading_port }}
@endif
@if ($order->maritimeShippingDetail?->discharge_port)
- Puerto de Descarga: {{ $order->maritimeShippingDetail->discharge_port }}
@endif
@if ($order->incoterm)
- Incoterm: {{ $order->incoterm->code }}
@endif
@if ($order->maritimeShippingDetail?->export_invoice_number)
- Nº de Factura de Exportación: {{ $order->maritimeShippingDetail->export_invoice_number }}
@endif

@if (! empty($trackingLinks))
## Seguimiento del envío{{ $carrierLabel ? " ({$carrierLabel})" : '' }}:
@foreach ($trackingLinks as $tracking)
- Contenedor {{ $tracking['containerNumber'] }}: [Ver seguimiento]({{ $tracking['url'] }})
@endforeach

@endif
@if (! empty($body))
## Notas:
{!! nl2br(e($body)) !!}

@endif
Saludos cordiales.

</x-mail::message>
