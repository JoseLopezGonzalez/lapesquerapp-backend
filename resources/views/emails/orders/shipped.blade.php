<x-mail::message>

# {{ $order->customer->name }}

<br>

**ES -** Su pedido con número **{{$order->formattedId}}** ha sido enviado. En breve recibirá su factura.



**IT -** Il suo ordine con il numero **{{$order->formattedId}}** è stato spedito. Riceverà presto la sua fattura.



**EN -** Your order with number **{{$order->formattedId}}** has been shipped. You will receive your invoice shortly.



**FR -** Votre commande avec le numéro **{{$order->formattedId}}** a été expédiée. Vous recevrez bientôt votre facture.



**PT -** Seu pedido com o número **{{$order->formattedId}}** foi enviado. Você receberá sua fatura em breve.

<br>

@if ($order->auxiliaryLines->isNotEmpty())
**Otros artículos incluidos en este envío:**

<x-mail::table>
| Artículo | Cantidad | Ud. | Precio unit. | Importe |
|:---------|:--------:|:---:|:------------:|--------:|
@foreach ($order->auxiliaryLines as $line)
| {{ $line->effective_description }} | {{ number_format($line->quantity, 3, ',', '.') }} | {{ $line->unit }} | {{ number_format($line->unit_price, 2, ',', '.') }} € | {{ number_format($line->subtotal, 2, ',', '.') }} € |
@endforeach
</x-mail::table>

<br>
@endif

Saludos / Saluti / Best regards / Cordialement / Atenciosamente.
</x-mail::message>