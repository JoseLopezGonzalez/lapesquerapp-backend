<x-mail::message>

# **{{ $documentName }} - #{{ $order->id }}**



Adjunto puedes encontrar el documento "**{{ $documentName }}**" correspondiente al pedido número **#{{ $order->id }}**.



## Detalles del Pedido:
- Cliente: {{ $order->customer->name }}
- Número de Pedido: {{ $order->formattedId }}
- Fecha de Carga: {{ date('d/m/Y', strtotime($order->load_date)) }}



Si necesita más información, no dude en contactarnos.


Saludos cordiales.

</x-mail::message>