<x-mail::message>

# {{ $order->customer->name }}

<br>

**Pedido Nº:** **#{{ $order->id }}**

**Fecha de Carga:** {{ date('d/m/Y', strtotime($order->load_date)) }}

<br>

Estimado/a comercial,

Adjuntamos los documentos correspondientes al pedido **#{{ $order->id }}** de
**{{ $order->customer->name }}**.

<br>

**Documentos incluidos:**
- Nota de Carga
- Packing List

<br>

Por favor, revisen la documentación adjunta para asegurarse de que toda la información esté correcta.

<br>

@if ($order->auxiliaryLines->isNotEmpty())
**Otros artículos (no pesqueros) de este pedido:**

<x-mail::table>
| Artículo | Cantidad | Ud. | Precio unit. | Importe |
|:---------|:--------:|:---:|:------------:|--------:|
@foreach ($order->auxiliaryLines as $line)
| {{ $line->effective_description }} | {{ number_format($line->quantity, 3, ',', '.') }} | {{ $line->unit }} | {{ number_format($line->unit_price, 2, ',', '.') }} € | {{ number_format($line->subtotal, 2, ',', '.') }} € |
@endforeach
</x-mail::table>

**Total artículos auxiliares (base):** {{ number_format($order->auxiliarySubtotal, 2, ',', '.') }} €

<br>
@endif

Si necesitan más información, pueden contactar directamente con el equipo de operaciones a [{{ tenantSetting('company.contact.email_operations') }}](mailto:{{ tenantSetting('company.contact.email_operations') }}).

<br>

Saludos cordiales

</x-mail::message>