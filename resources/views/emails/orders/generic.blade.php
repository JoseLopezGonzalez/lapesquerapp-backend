<x-mail::message>

# **{{ $documentName }} - #{{ $order->id }}**



Adjunto puedes encontrar el documento "**{{ $documentName }}**" correspondiente al pedido número **#{{ $order->id }}**.



## Detalles del Pedido:
- Cliente: {{ $order->customer->name }}
- Número de Pedido: {{ $order->formattedId }}
- Fecha de Carga: {{ date('d/m/Y', strtotime($order->load_date)) }}

@if ($order->auxiliaryLines->isNotEmpty())
**Otros artículos incluidos:**

<x-mail::table>
| Artículo | Cantidad | Ud. | Precio unit. | Importe |
|:---------|:--------:|:---:|:------------:|--------:|
@foreach ($order->auxiliaryLines as $line)
| {{ $line->effective_description }} | {{ number_format($line->quantity, 3, ',', '.') }} | {{ $line->unit }} | {{ number_format($line->unit_price, 2, ',', '.') }} € | {{ number_format($line->subtotal, 2, ',', '.') }} € |
@endforeach
</x-mail::table>
@endif

Si necesita más información, no dude en contactarnos.


Saludos cordiales.

</x-mail::message>