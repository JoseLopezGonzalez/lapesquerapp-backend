<x-mail::message>

# {{ $order->customer->name }}

<br>

**ES -** Su pedido de exportación con número **{{ $order->formattedId }}** ha sido cargado y despachado. Adjuntamos la documentación correspondiente.



**EN -** Your export order with number **{{ $order->formattedId }}** has been loaded and dispatched. Please find the corresponding documentation attached.

<br>

{!! nl2br(e($body)) !!}

## Detalles del Pedido:
- Cliente: {{ $order->customer->name }}
- Número de Pedido: {{ $order->formattedId }}
@if ($order->maritimeShippingDetail?->vessel_name)
- Buque: {{ $order->maritimeShippingDetail->vessel_name }}
@endif
@if ($order->maritimeShippingDetail?->voyage_number)
- Nº de Viaje: {{ $order->maritimeShippingDetail->voyage_number }}
@endif

@if (! empty($trackingLinks))
## Seguimiento del envío{{ $carrierLabel ? " ({$carrierLabel})" : '' }}:
@foreach ($trackingLinks as $tracking)
- Contenedor {{ $tracking['containerNumber'] }}: [Ver seguimiento]({{ $tracking['url'] }})
@endforeach

@endif
Saludos cordiales.

</x-mail::message>
