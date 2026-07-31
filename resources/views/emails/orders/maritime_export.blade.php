<x-mail::message>

# {{ $order->customer->name }}

<br>

**ES -** Su pedido con número **{{ $order->formattedId }}** ha sido cargado y despachado. Le enviamos la documentación disponible en este momento; si recibimos el resto de documentación pendiente, se la haremos llegar en un envío posterior.



**EN -** Your order with number **{{ $order->formattedId }}** has been loaded and dispatched. We are sending you the documentation currently available; if we receive any pending documentation, we will forward it in a follow-up email.

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
