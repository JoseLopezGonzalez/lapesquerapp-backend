<x-mail::message>

# {{ optional($order->transport)->name ?? '-' }}

## &nbsp;

## Detalles del Envío:

- **{{ optional($order->customer)->alias ?? '-' }}**
- **Número de Pedido:** {{ $order->formattedId }}
- **Fecha de carga:** {{ !empty($order->load_date) ? \Illuminate\Support\Carbon::parse($order->load_date)->format('d/m/Y') : '-' }}
- **Destino:** {!! nl2br(e($order->shipping_address)) !!}

## &nbsp;

<x-mail::table>
    | Nº Palet | Cajas | Peso Total |
    |:-----------:|:-----------:|:------------:|
    @foreach ($order->pallets as $pallet)
    | #{{ $pallet->id }} | {{ $pallet->numberOfBoxes }} | {{ number_format($pallet->netWeight, 2, ',', '.') }} kg |
    @endforeach
</x-mail::table>

## &nbsp;

## Documentación Adjunta:

<x-mail::panel>
    Se adjunta la documentación relevante necesaria para la manipulación y transporte de las mercancías.
</x-mail::panel>

## &nbsp;

## Observaciones:

Por favor, revisen los documentos adjuntos para aseguraros que todos los detalles son correctos y que tienen todo lo necesario.

*Si encuentran alguna discrepancia o necesitan más información, no duden en contactarnos a [{{ config('company.contact.email_orders') }}](mailto:{{ config('company.contact.email_orders') }}) ({{ config('company.contact.phone_orders') }})*

Saludos.
</x-mail::message>