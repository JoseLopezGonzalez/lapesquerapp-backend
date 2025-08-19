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

Si necesitan más información, pueden contactar directamente con el equipo de operaciones a [{{ tenantSetting('company.contact.email_operations') }}](mailto:{{ tenantSetting('company.contact.email_operations') }}).

<br>

Saludos cordiales

</x-mail::message>