<!DOCTYPE html>
<html>

<head>
    <title>Nota de Carga</title>
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

        .break-before-page {
            page-break-before: always;
        }
    </style>
</head>

<body class="bg-white text-black text-xs">
    <div class="max-w-[210mm] mx-auto p-6 bg-white rounded min-h-screen">
        <!-- ENCABEZADO -->
        <div class="flex justify-between items-end mb-6 ">
            <div class="flex items-center gap-2">
                <div>
                    <h1 class="text-md font-bold">{{ tenantSetting('company.name') }}</h1>
                    <p>{{ tenantSetting('company.address.street') }} - {{ tenantSetting('company.address.postal_code') }}
                        {{ tenantSetting('company.address.city') }}
                    </p>
                    <p>Tel: {{ tenantSetting('company.contact.phone_admin') }}</p>
                    <p>{{ tenantSetting('company.contact.email_admin') }}</p>
                    <p>{{ tenantSetting('company.sanitary_number') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-4">
                <div class="  rounded  text-end">
                    <h2 class="text-lg font-bold ">Nota de Carga</h2>
                    <p class=" font-medium"><span class="">{{ $entity->formattedId }}</span></p>
                    <p class=" font-medium">Fecha:<span class="">
                            {{ date('d/m/Y', strtotime($entity->load_date)) }}
                        </span></p>
                    <p class=" font-medium">Buyer Reference:{{ $entity->buyer_reference }}</p>
                </div>
                <div class="flex flex-col items-center">
                    <div class="p-1 border rounded flex items-center justify-center bg-white">
                        <img alt='Barcode Generator TEC-IT'
                            src="{{ 'https://barcode.tec-it.com/barcode.ashx?data=Pedido%3A' . $entity->id . '&code=QRCode&eclevel=L' }}"
                            class="w-[4.1rem] h-[4.1rem]" />
                    </div>
                </div>
            </div>
        </div>

        <!-- DIRECCIONES (ENVÍO A LA IZQUIERDA, FACTURACIÓN A LA DERECHA) -->
        <div class="flex gap-6 mb-6 w-full">
            <div class="border rounded-lg overflow-hidden bg-gray-50 flex-1 ">
                <div class="font-bold p-2 bg-gray-800 w-full border-b text-white">DIRECCIÓN DE ENVÍO</div>
                <div class="p-4 ">
                    <p>{!! nl2br(e($entity->shipping_address)) !!}</p>
                </div>
            </div>

            <div class="border rounded-lg overflow-hidden bg-gray-50 text-right w-[340px]">
                <div class="font-bold p-2  w-full  text-white"></div>
                <div class="p-4 ">
                    <p>{!! nl2br(e($entity->billing_address)) !!}</p>
                </div>
            </div>
        </div>

        <!-- DETALLE DE PRODUCTOS -->
        <h3 class="font-bold mb-2">DETALLE DE PRODUCTOS</h3>
        <div class="border rounded-lg overflow-hidden">
            <table class="w-full text-xs">
                <thead class="border-b bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Producto</th>
                        <th class="p-2 text-center">Código GTIN</th>
                        <th class="p-2 text-center">Lote</th>
                        <th class="p-2 text-center">Cajas</th>
                        <th class="p-2 text-center">Peso Neto</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $rowIndex = 0; // Controlador manual para alternar colores
                    @endphp

                    @foreach ($entity->productsWithLotsDetails as $productLine)
                        @php
                            $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                            $rowIndex++; // Incrementamos el contador de filas
                        @endphp

                        <!-- Fila principal del producto -->
                        <tr class="{{ $rowClass }}">
                            <td class="p-2 py-1">{{ $productLine['product']['name'] }}</td>
                            <td class="p-2 py-1">{{ $productLine['product']['boxGtin'] }}</td>
                            <td class="p-2 py-1">
                                {{ count($productLine['lots']) === 1 ? $productLine['lots'][0]['lot'] : '' }}
                            </td>
                            <td class="p-2 py-1">{{ $productLine['product']['boxes'] }}</td>
                            <td class="p-2 py-1">
                                {{ number_format($productLine['product']['netWeight'], 2, ',', '.') }} kg
                            </td>
                        </tr>

                        <!-- Fila con información de la especie -->
                        @php
                            $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                            $rowIndex++;
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="pl-5 p-2 py-1 text-[10px]" colspan="5">
                                <i>
                                    {{ $productLine['product']['species']['name'] }}
                                    `{{ $productLine['product']['species']['scientificName'] }} -
                                    {{ $productLine['product']['species']['fao'] }}`
                                    - {{ $productLine['product']['fishingGear'] }} /
                                    {{ $productLine['product']['captureZone'] }}
                                </i>
                            </td>
                        </tr>

                        <!-- Si hay más de un lote, se imprimen en filas separadas -->
                        @if (count($productLine['lots']) > 1)
                            @foreach ($productLine['lots'] as $lot)
                                @php
                                    $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                    $rowIndex++;
                                @endphp
                                <tr class="{{ $rowClass }} text-[10px]">
                                    <td class="p-2 py-1"></td>
                                    <td class="text-md text-end">↪︎</td>
                                    <td class="p-2 py-1">{{ $lot['lot'] }}</td>
                                    <td class="p-2 py-1">{{ $lot['boxes'] }}</td>
                                    <td class="p-2 py-1">{{ number_format($lot['netWeight'], 2, ',', '.') }} kg</td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>

                <!-- TOTALES -->
                <tfoot class="border-t bg-gray-100">
                    <tr>
                        <td class="p-2 font-semibold">Total</td>
                        <td class="p-2 text-center"></td>
                        <td class="p-2 text-center"></td>
                        <td class="p-2 text-center">{{ $entity->totalBoxes }}</td>
                        <td class="p-2 text-center">{{ number_format($entity->totalNetWeight, 2, ',', '.') }} kg</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- INFORMACIÓN ADICIONAL -->
        <div class="grid grid-cols-2 gap-4 mt-4 break-inside-avoid">
            <div class="border rounded-lg p-4 py-2 bg-gray-50">
                <h3 class="font-bold mb-2">INCOTERM</h3>
                <p><strong>{{ $entity->incoterm->code }}</strong> ({{ $entity->incoterm->description }})</p>
            </div>

            <div class="border rounded-lg p-4 py-2 bg-gray-50">
                <h3 class="font-bold mb-2">NÚMERO DE PALETS</h3>
                <p class="">{{ $entity->numberOfPallets }}</p>
            </div>
        </div>


    </div>
</body>

</html>