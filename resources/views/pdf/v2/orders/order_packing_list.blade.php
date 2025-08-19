<!-- resources/views/pdf/delivery_note.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <title>Packing List</title>
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

<body class="h-full ">

    <div class="flex flex-col max-w-[210mm]  mx-auto p-6 bg-white rounded text-black text-xs min-h-screen ">
        <div class="flex justify-between items-end mb-6 ">
            <div class="flex items-center gap-2">
                <div>
                    <h1 class="text-md font-bold">{{ tenantSetting('company.name') }}</h1>
                    <p>{{ tenantSetting('company.address.street') }} - {{ tenantSetting('company.address.postal_code') }}
                        {{ tenantSetting('company.address.city') }}</p>
                    <p>Tel: {{ tenantSetting('company.contact.phone_admin') }}</p>
                    <p>{{ tenantSetting('company.contact.email_admin') }}</p>
                    <p>{{ tenantSetting('company.sanitary_number') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-4">
                <div class="  rounded  text-end">
                    <h2 class="text-lg font-bold ">Packing List</h2>
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

        <div class="mb-6 bg-gray-50 p-3 border border-gray-200 rounded-lg text-xs">
            <h3 class="font-bold mb-1">Interpretación de Lote</h3>
            <p class="grid grid-cols-4 gap-2">
                <span>
                    <b>1.</b> Fecha de captura o producción (DDMMAA)
                </span>
                <span>
                    <b>2.</b> Código FAO de la especie
                </span>
                <span>
                    <b>3.</b> Código de zona de captura
                </span>
                <span>
                    <b>4.</b> Código interno de producción
                </span>
            </p>
            <p class="mt-2 text-[10px] italic">
                Ejemplo: 150523HKE01002 = Capturado el 15/05/23, Merluza (HKE), Zona 01, Producción 002
            </p>
        </div>


        <div class="mb-6">
            @foreach ($entity->pallets as $pallet)
                <div class="mb-8 break-after-page">
                    <div class="bg-gray-800 text-white p-2 flex justify-between items-center rounded-t-lg">
                        <h3 class="font-bold">Palet #{{ $pallet->id }}</h3>
                        {{--  <div class="text-sm">
                            <span class="mr-4">Peso Neto: {{ $pallet->netWeight }} kg</span>
                            <span>Cajas: {{ $pallet->numberOfBoxes }} </span>
                        </div> --}}
                    </div>

                    <div class="border border-gray-300 border-t-0 p-2 mb-2 bg-gray-50 rounded-b-lg">
                        <span class="font-semibold">Lotes en este palet: </span>
                        @foreach ($pallet->lots as $lot)
                            <span class="inline-block bg-gray-200 px-2 py-1 mr-2 mb-1 rounded-full text-xs">
                                {{ $lot }}
                            </span>
                        @endforeach
                    </div>

                    <div class="w-full mb-2 rounded-lg overflow-hidden border">
                        <table class="w-full ">
                            <thead class="border-b">
                                <tr class="bg-gray-100">
                                    <th class="p-2 text-left">Producto</th>
                                    <th class="p-2 text-center">Cajas</th>
                                    <th class="p-2 text-center">Peso Neto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pallet->summary as $productDetail)
                                    <tr class="{{ $loop->even ? 'bg-white' : 'bg-gray-50' }}">
                                        <td class=" p-2 font-semibold">
                                            {{ $productDetail['product']->article->name }}</td>
                                        <td class=" p-2 text-center">
                                            {{ $productDetail['boxes'] }}
                                        </td>
                                        <td class=" p-2 text-center">
                                            {{ number_format($productDetail['netWeight'], 2, ',', '.') }} kg
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t">
                                <tr class="bg-gray-100">
                                    <td class=" p-2 font-semibold">Total</td>
                                    <td class=" p-2 text-center">{{ $pallet->numberOfBoxes }}
                                    </td>
                                    <td class=" p-2 text-center">{{ number_format($pallet->netWeight, 2, ',', '.') }}
                                        kg</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-2 mb-4">
                        @foreach ($pallet->summary as $productDetail)
                            <div class="border border-gray-300 p-2 bg-white rounded-lg">
                                <p class="text-xs mb-1 font-semibold">
                                    {{ $productDetail['product']->article->name }}
                                </p>

                                <div class="h-10 bg-gray-800 relative flex items-center justify-center">
                                    <div class="absolute inset-0 flex items-center">
                                        <img alt='Barcode Generator TEC-IT'
                                            src={{ 'https://barcode.tec-it.com/barcode.ashx?data=(01)' . $productDetail['product']->box_gtin . '&code=Code128&translate-esc=on' }}
                                            class="h-full" />
                                    </div>
                                    <span class="text-xs text-white z-10 bg-gray-800 px-1">GS1-128</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{--  NUEVO: Listado detallado de cajas  --}}

                    <div class="border border-gray-300 rounded-lg overflow-hidden">
                        <table class="w-full border-collapse text-xs">
                            <thead class=" bg-white border-b">
                                <tr class="bg-gray-100">
                                    <th class=" p-1 text-center">ID</th>
                                    <th class=" p-1 text-left">Producto</th>
                                    <th class=" p-1 text-center">Lote</th>
                                    <th class=" p-1 text-center">Peso Neto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pallet->boxes as $box)
                                    <tr class="{{ $loop->even ? 'bg-white' : 'bg-gray-50' }}">
                                        <td class=" p-1 font-mono">{{ $box->box->id }}</td>
                                        <td class=" p-1">
                                            {{ $box->box->product->article->name }}</td>
                                        <td class=" p-1 text-center font-mono text-xs">
                                            {{ $box->box->lot }}</td>
                                        <td class=" p-1 text-center font-mono text-xs">
                                            {{ number_format($box->box->net_weight, 2, ',', '.') }} kg
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            @endforeach
        </div>

       


    </div>
</body>

</html>
