<!DOCTYPE html>
<html>

<head>
    <title>Confirmación de pedido</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'DejaVu Sans';
        }

        @page {
            size: A4 portrait;
        }

        .bold-first-line::first-line {
            font-weight: bold;
        }
    </style>
</head>

<body class="bg-white text-black text-xs p-4">
    <div class="max-w-[210mm] mx-auto  bg-white rounded ">
        <!-- ENCABEZADO -->
        <div class="flex justify-between items-end mb-2">
            <div>
                <h1 class="text-md font-bold">{{ config('company.name') }}</h1>
                <p>{{ config('company.address.street') }} - {{ config('company.address.postal_code') }}
                    {{ config('company.address.city') }}
                </p>
                <p>Tel: {{ config('company.contact.phone_admin') }}</p>
                <p>{{ config('company.contact.email_admin') }}</p>
                <p>{{ config('company.sanitary_number') }}</p>
            </div>

            <div class="flex items-start gap-4">
                <div class="text-end">
                    <h2 class="text-lg font-bold">Confirmación de pedido</h2>
                    <p class="font-medium">{{ $entity->formattedId }}</p>
                    <p class="font-medium">Fecha: {{ date('d/m/Y', strtotime($entity->load_date)) }}</p>
                    <p class="font-medium">Buyer Reference: {{ $entity->buyer_reference }}</p>
                </div>
                <div class="p-1 border rounded bg-white">
                    <img src="{{ 'https://barcode.tec-it.com/barcode.ashx?data=Pedido%3A' . $entity->id . '&code=QRCode&eclevel=L' }}"
                        class="w-[4.1rem] h-[4.1rem]" alt="QR Code" />
                </div>
            </div>
        </div>

        <!-- CLIENTE Y TRANSPORTE -->
        <div class="grid grid-cols-2 gap-2 mb-2">
            <div class="border rounded-lg overflow-hidden bg-gray-50">
                <div class="font-bold p-2 bg-gray-800 text-white">DATOS DEL CLIENTE</div>
                <div class="p-4">
                    <p><span class="font-medium">Nombre:</span> {{ $entity->customer->name }}</p>
                    <p><span class="font-medium">NIF/CIF:</span> {{ $entity->customer->vat_number }}</p>
                    <p class="font-medium mt-2">Correos electrónicos:</p>
                    <ul class="list-disc pl-5">
                        @foreach ($entity->emailsArray as $email)
                            <li>{{ $email }}</li>
                        @endforeach
                        @foreach ($entity->ccEmailsArray as $email)
                            <li>{{ $email }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="border rounded-lg overflow-hidden bg-gray-50">
                <div class="font-bold p-2 bg-gray-800 text-white">DATOS DE TRANSPORTE</div>
                <div class="p-4">
                    <p><span class="font-medium">Empresa:</span> {{ $entity->transport->name }}</p>
                    <p class="font-medium mt-2">Correos electrónicos:</p>
                    <ul class="list-disc pl-5">
                        @foreach ($entity->transport->emailsArray as $email)
                            <li>{{ $email }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <!-- DIRECCIONES -->
        <div class="grid grid-cols-2 gap-2 mb-2">
            <div class="border rounded-lg bg-gray-50 overflow-hidden">
                <div class="font-bold p-2 bg-gray-800 text-white">DIRECCIÓN DE FACTURACIÓN</div>
                <div class="p-4">{!! nl2br(e($entity->billing_address)) !!}</div>
            </div>
            <div class="border rounded-lg bg-gray-50 overflow-hidden">
                <div class="font-bold p-2 bg-gray-800 text-white">DIRECCIÓN DE ENVÍO</div>
                <div class="p-4">{!! nl2br(e($entity->shipping_address)) !!}</div>
            </div>
        </div>

        <!-- CONDICIONES GENERALES -->
        <div class="border p-4 rounded-lg bg-gray-50 mb-6 text-[10px]">
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <p class="font-bold">FORMA DE PAGO</p>
                    <p>{{ $entity->payment_term->name }}</p>
                </div>
                <div>
                    <p class="font-bold">INCOTERM</p>
                    <p>{{ $entity->incoterm->code }} - {{ $entity->incoterm->description }}</p>
                </div>
                <div>
                    <p class="font-bold">NÚMERO DE PALETS</p>
                    <p>{{ $entity->numberOfPallets }}</p>
                </div>
            </div>
        </div>

        <!-- DETALLE DE PRODUCTOS -->
        <h3 class="font-bold mb-2">DETALLE DE PRODUCTOS</h3>
        <div class="border rounded-lg overflow-hidden">
            <table class="w-full text-xs text-nowrap">
                <thead class="border-b bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Producto</th>
                        <th class="p-2 text-center">Cajas</th>
                        <th class="p-2 text-center">Cantidad</th>
                        <th class="p-2 text-center">Precio</th>
                        <th class="p-2 text-center">Subtotal</th>
                        <th class="p-2 text-center">IVA</th>
                        <th class="p-2 text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalBoxes = 0;
                        $totalQuantity = 0;
                        $totalSubtotal = 0;
                        $totalTax = 0;
                        $totalAmount = 0;
                    @endphp

                    @foreach ($entity->plannedProductDetails as $detail)
                        @php
                            $subtotal = $detail->unit_price * $detail->quantity;
                            $iva = $subtotal * ($detail->tax->rate / 100);
                            $total = $subtotal + $iva;
                            $totalBoxes += $detail->boxes;
                            $totalQuantity += $detail->quantity;
                            $totalSubtotal += $subtotal;
                            $totalTax += $iva;
                            $totalAmount += $total;
                        @endphp
                        <tr>
                            <td class="p-2 text-wrap">{{ $detail->product->name }}</td>
                            <td class="p-2 text-center">{{ $detail->boxes }}</td>
                            <td class="p-2 text-center">{{ number_format($detail->quantity, 2, ',', '.') }} kg</td>
                            <td class="p-2 text-center">{{ number_format($detail->unit_price, 2, ',', '.') }} €</td>
                            <td class="p-2 text-center">{{ number_format($subtotal, 2, ',', '.') }} €</td>
                            <td class="p-2 text-center">{{ number_format($detail->tax->rate, 2, ',', '.') }}%</td>
                            <td class="p-2 text-center">{{ number_format($total, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t bg-gray-100 font-semibold">
                    <tr>
                        <td class="p-2">Totales</td>
                        <td class="p-2 text-center">{{ $totalBoxes }}</td>
                        <td class="p-2 text-center">{{ number_format($totalQuantity, 2, ',', '.') }} kg</td>
                        <td></td>
                        <td class="p-2 text-center">{{ number_format($totalSubtotal, 2, ',', '.') }} €</td>
                        <td></td>
                        <td class="p-2 text-center">{{ number_format($totalAmount, 2, ',', '.') }} €</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="grid-cols-2 grid mt-4 gap-4">
            <!-- OBSERVACIONES Y CONFIRMACIÓN -->
            <div class="p-4 bg-gray-50 rounded-lg border text-xs space-y-3">
                <p class="font-medium text-xs h-full">
                    Para la aprobación definitiva del pedido, es necesario enviar este documento firmado y sellado a
                    {{ config('company.name') }} a la dirección de correo
                    <strong>{{ config('company.contact.email_orders') }}</strong>.
                </p>
            </div>


            <!-- ESPACIO PARA FIRMA Y SELLO -->
            <div class=" border rounded-lg p-6 flex flex-col justify-between">
                <p class="font-bold text-center">Firma y Sello</p>
                <div className='h-4'></div>
                <div class="border-t border-gray-400 mt-4"></div>
            </div>




        </div>
        <!--  <div class="text-[10px] text-center border-t pt-2 mt-4 text-gray-500 ">
            No se admitirán devoluciones ni reclamaciones relacionadas con la mercancía transcurridas 48 horas desde su
            recepción.
        </div> -->


    </div>


</body>
<footer class="text-[10px] text-center mt-16 border-t pt-2 text-gray-500 leading-snug">
    <span class="font-medium text-black">
        No se admitirán devoluciones ni reclamaciones relacionadas con la mercancía
        transcurridas 48 horas desde su recepción.
    </span><br>
    {{ config('company.name') }} · CIF {{ config('company.cif') }} ·
    {{ config('company.address.street') }} ·
    {{ config('company.address.postal_code') }} {{ config('company.address.city') }},
    {{ config('company.address.province') }} ·
    {{ tenantSetting('company.website_url') }}
</footer>






</html>