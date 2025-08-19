<!DOCTYPE html>
<html>

<head>
    <title>Nota Valorada</title>
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

<body class="bg-white text-black text-xs">
    <div class="max-w-[210mm] mx-auto p-6 bg-white rounded min-h-screen">
        <!-- ENCABEZADO -->
        <div class="flex justify-between items-end mb-6">
            <div>
                <h1 class="text-md font-bold">{{ tenantSetting('company.name') }}</h1>
                <p>
                    {{ tenantSetting('company.address.street') }} -
                    {{ tenantSetting('company.address.postal_code') }} {{ tenantSetting('company.address.city') }}
                </p>
                <p>Tel: {{ tenantSetting('company.contact.phone_admin') }}</p>
                <p>{{ tenantSetting('company.contact.email_admin') }}</p>
                <p>{{ tenantSetting('company.sanitary_number') }}</p>
            </div>

            <div class="flex items-start gap-4">
                <div class="text-end">
                    <h2 class="text-lg font-bold">Nota de carga valorada</h2>
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

        <!-- DIRECCIONES -->
        <div class="flex gap-6 mb-6">
            <div class="border rounded-lg bg-gray-50 flex-1 overflow-hidden">
                <div class="font-bold p-2 bg-gray-800 text-white">DIRECCIÓN DE ENVÍO</div>
                <div class="p-4">{!! nl2br(e($entity->shipping_address)) !!}</div>
            </div>
            <div class="border rounded-lg bg-gray-50 w-[340px] text-right overflow-hidden">
                <div class="font-bold p-2 bg-gray-800 text-white">DIRECCIÓN DE FACTURACIÓN</div>
                <div class="p-4">{!! nl2br(e($entity->billing_address)) !!}</div>
            </div>
        </div>

        <!-- DETALLE DE PRODUCTOS -->
        <h3 class="font-bold mb-2">DETALLE DE PRODUCTOS</h3>
        <div class="border rounded-lg overflow-hidden">
            <table class="w-full text-xs">
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
                        $totalNetWeight = 0;
                        $totalSubtotal = 0;
                        $totalTax = 0;
                        $totalAmount = 0;
                        $rowIndex = 0;
                    @endphp

                    @foreach ($entity->productDetails as $detail)
                        @php
                            $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                            $rowIndex++;
                            $taxAmount = $detail['subtotal'] * ($detail['tax']['rate'] / 100);
                            $totalBoxes += $detail['boxes'];
                            $totalNetWeight += $detail['netWeight'];
                            $totalSubtotal += $detail['subtotal'];
                            $totalTax += $taxAmount;
                            $totalAmount += $detail['total'];
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="p-2 py-1">{{ $detail['product']['name'] }}</td>
                            <td class="p-2 py-1 text-center">{{ $detail['boxes'] }}</td>
                            <td class="p-2 py-1 text-center">{{ number_format($detail['netWeight'], 2, ',', '.') }} kg
                            </td>
                            <td class="p-2 py-1 text-center">{{ number_format($detail['unitPrice'], 2, ',', '.') }} €</td>
                            <td class="p-2 py-1 text-center">{{ number_format($detail['subtotal'], 2, ',', '.') }} €</td>
                            <td class="p-2 py-1 text-center">{{ number_format($detail['tax']['rate'], 2, ',', '.') }}%
                            </td>
                            <td class="p-2 py-1 text-center">{{ number_format($detail['total'], 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
                <!-- TOTALES -->
                <tfoot class="border-t bg-gray-100 font-semibold">
                    <tr>
                        <td class="p-2">Totales</td>
                        <td class="p-2 text-center">{{ $totalBoxes }}</td>
                        <td class="p-2 text-center">{{ number_format($totalNetWeight, 2, ',', '.') }} kg</td>
                        <td class="p-2 text-center"></td>
                        <td class="p-2 text-center">{{ number_format($totalSubtotal, 2, ',', '.') }} €</td>
                        <td class="p-2 text-center"></td>
                        <td class="p-2 text-center">{{ number_format($totalAmount, 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="p-2 text-end">Base Imponible:</td>
                        <td colspan="2" class="p-2 text-end">{{ number_format($totalSubtotal, 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="p-2 text-end">IVA Total:</td>
                        <td colspan="2" class="p-2 text-end">{{ number_format($totalTax, 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="p-2 text-end">TOTAL:</td>
                        <td colspan="2" class="p-2 text-end font-bold">
                            {{ number_format($totalAmount, 2, ',', '.') }} €
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- OBSERVACIONES -->
        <div class="mt-6 p-4 bg-gray-50 rounded-lg border">
            <p class="text-xs italic">
                Documento de carga valorado para confirmación de precios y cantidades. Este documento no es un albarán
                ni factura contable.
            </p>
        </div>
    </div>
</body>

</html>