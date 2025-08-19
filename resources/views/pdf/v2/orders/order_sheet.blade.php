<!-- resources/views/pdf/delivery_note.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <title>Hoja de Pedido</title>
    {{-- Tailwind no funciona, lo cojo todo directamente de un cdn --}}

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'DejaVu Sans';
        }

        .bold-first-line::first-line {
            font-weight: bold;
        }

        @page {
            size: A4 portrait;
            /*  margin: 20mm; */
        }
    </style>
    {{-- getProductsWithLotsDetailsBySpeciesAndCaptureZoneAttribute --}}
</head>

<body class="h-full">

    <div class="flex flex-col max-w-[210mm]  mx-auto p-6 bg-white rounded text-black text-xs min-h-screen ">
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
                    <h2 class="text-lg font-bold ">Hoja de pedido</h2>
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
        <div class="grid grid-cols-2 gap-2 mb-2 text-xs">
            <div class="space-y-2">
                <div class="border rounded-lg overflow-hidden bg-gray-50">
                    <div class="font-bold  mb-2 w-full p-2 bg-gray-800 border-b text-white">DATOS DEL CLIENTE</div>
                    <div class=" space-y-1 p-4 pt-0">
                        <p><span class="font-medium">Nombre:</span> {{ $entity->customer->name }}</p>
                        <p><span class="font-medium">NIF/CIF:</span>{{ $entity->customer->vat_number }}</p>

                        <p class="font-medium mt-2">Correos electrónicos:</p>
                        <ul class="list-disc pl-5">
                            {{-- $entity->emailsArray --}}
                            @foreach ($entity->emailsArray as $email)
                                <li>{{ $email }}</li>
                            @endforeach
                            {{-- $entity->ccEmailsArray --}}
                            @foreach ($entity->ccEmailsArray as $email)
                                <li>{{ $email }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="border rounded-lg overflow-hidden bg-gray-50">
                    <div class="font-bold  mb-2 w-full p-2 bg-gray-800 border-b text-white">DATOS DE TRANSPORTE</div>
                    <div class=" space-y-1 p-4 pt-0">
                        <p><span class="font-medium">Empresa:</span> {{ $entity->transport->name }}</p>
                        <p class="font-medium mt-2">Correos electrónicos:</p>
                        <ul class="list-disc pl-5">
                            {{-- $entity->transport->emailsArray y $entity->ccEmailsArray --}}
                            @foreach ($entity->transport->emailsArray as $email)
                                <li>{{ $email }}</li>
                            @endforeach
                            @foreach ($entity->transport->ccEmailsArray as $email)
                                <li>{{ $email }}</li>
                            @endforeach

                        </ul>
                    </div>
                </div>
            </div>
            <div class="border rounded-lg overflow-hidden bg-gray-50">
                <div class="font-bold  mb-2 w-full p-2 bg-gray-800 border-b text-white">DIRECCIONES</div>
                <div class=" space-y-1 p-4 pt-0">
                    <h3 class="font-bold  mb-2">DIRECCIÓN DE FACTURACIÓN</h3>
                    <p class="">
                        {!! nl2br($entity->billing_address) !!}
                    </p>
                    <hr class="my-4 border-dashed border-slate-300" />
                    <h3 class="font-bold  mb-2">DIRECCIÓN DE ENVÍO</h3>
                    <p class="">
                        {!! nl2br($entity->shipping_address) !!}
                    </p>
                </div>
            </div>
        </div>

        <!-- SECCIÓN COMPACTADA: INCOTERM, FORMA DE PAGO Y PALETS -->
        <div class="border p-4 py-2 rounded-lg bg-gray-50 mb-2 text-[10px]">
            <div class="grid grid-cols-3 gap-4 divide-x-gray-800">
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

        <!-- OBSERVACIONES DIVIDIDAS EN TARJETAS -->
        <div class="grid grid-cols-3 gap-2 text-[10px] mb-6">
            <div class="border p-4 rounded-lg bg-gray-50">
                <h3 class="font-bold mb-2">OBSERVACIONES PRODUCCIÓN</h3>
                <p>
                    {!! nl2br(e($entity->production_notes)) !!}
                </p>
            </div>
            <div class="border p-4 rounded-lg bg-gray-50">
                <h3 class="font-bold mb-2">OBSERVACIONES CONTABILIDAD</h3>
                <p>
                    {!! nl2br(e($entity->accounting_notes)) !!}
                </p>
            </div>
            <div class="border p-4 rounded-lg bg-gray-50">
                <h3 class="font-bold mb-2">OBSERVACIONES TRANSPORTE</h3>
                <p>
                    {!! nl2br(e($entity->transportation_notes)) !!}
                </p>
            </div>
        </div>

        <div class=" mb-6 ">
            <h3 class="font-bold mb-2">DETALLE DE PRODUCTOS</h3>
            <div class="border border-gray-300 rounded-lg overflow-hidden">
                <table class="w-full text-xs h-full ">
                    <thead class="border-b bg-white ">
                        <tr class="bg-gray-100">
                            <th class="p-2 font-bold text-start">Producto</th>
                            <th class="p-2 font-bold text-start">Código GTIN</th>
                            <th class="p-2 font-bold text-start">Lote</th>
                            <th class="p-2 font-bold text-start">Cajas</th>
                            <th class="p-2 font-bold text-start">Peso Neto</th>
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
                                <td class="p-2 py-1">{{ $productLine['product']['article']['name'] }}</td>
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
                                        <td class="p-2 py-1">{{ number_format($lot['netWeight'], 2, ',', '.') }} kg
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach

                        <tr className='font-bold '>
                            <td class="p-2 py-1 border-t bg-gray-100"></td>
                            <td class="p-2 py-1 border-t bg-gray-100"></td>
                            <td class="p-2 py-1 border-t bg-gray-100">Total</td>
                            <td class="p-2 py-1 border-t bg-gray-100">{{ $entity->totalBoxes }} </td>
                            <td class="p-2 py-1 border-t bg-gray-100">
                                {{ number_format($entity->totalNetWeight, 2, ',', '.') }} kg
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>

</html>