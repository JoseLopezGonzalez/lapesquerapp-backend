<x-mail::message>

# {{ optional($order->externalProcessor)->name ?? 'Maquilador' }}

## &nbsp;

## Documentación para expedición:

- **Número de Pedido:** {{ $order->formattedId }}
- **Fecha de carga:** {{ !empty($order->load_date) ? \Illuminate\Support\Carbon::parse($order->load_date)->format('d/m/Y') : '-' }}
- **Transporte:** {{ optional($order->transport)->name ?? '-' }}

## &nbsp;

<x-mail::table>
    | Nº Palet | Cajas | Peso Neto |
    |:-----------:|:-----------:|:------------:|
    @foreach ($order->pallets as $pallet)
    | #{{ $pallet->id }} | {{ $pallet->numberOfBoxes }} | {{ number_format($pallet->netWeight, 2, ',', '.') }} kg |
    @endforeach
</x-mail::table>

## &nbsp;

## Documentación Adjunta:

<x-mail::panel>
    Se adjunta la documentación necesaria para la carga y expedición de la mercancía. Por favor, utilicen los letreros y el CMR adjuntos para identificar los palets y acompañar el envío.
</x-mail::panel>

## &nbsp;

*Si tienen alguna consulta, pueden contactarnos en [{{ tenantSetting('company.contact.email_orders') }}](mailto:{{ tenantSetting('company.contact.email_orders') }}) ({{ tenantSetting('company.contact.phone_orders') }})*

Saludos.
</x-mail::message>
