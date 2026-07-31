<x-mail::message>

# Documentación de Exportación - Pedido #{{ $order->id }}

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

Saludos cordiales.

</x-mail::message>
