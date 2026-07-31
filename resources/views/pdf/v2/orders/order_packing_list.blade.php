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

        <div class="mb-6">
            @foreach ($entity->pallets as $pallet)
                <div class="mb-8 break-after-page">
                    <h2 class="text-lg font-bold border-b-2 border-gray-800 pb-1 mb-3">Palet #{{ $pallet->id }}</h2>

                    <div class="w-full mb-2 rounded-lg overflow-hidden border border-gray-300">
                        <div class="p-2 bg-gray-50 border-b border-gray-300 space-y-1">
                            <p>
                                <span class="font-semibold">Lotes en este palet: </span>
                                @foreach ($pallet->lots as $lot)
                                    <span class="inline-block bg-gray-200 px-2 py-1 mr-2 mb-1 rounded-full text-xs">
                                        {{ $lot }}
                                    </span>
                                @endforeach
                            </p>
                            <p>
                                <span class="font-semibold">Productos en este palet: </span>
                                @foreach ($pallet->summary as $productDetail)
                                    <span class="inline-block bg-gray-200 px-2 py-1 mr-2 mb-1 rounded-full text-xs">
                                        {{ $productDetail['product']->name }}
                                    </span>
                                @endforeach
                            </p>
                        </div>
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
                                            {{ $productDetail['product']->name }}</td>
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
                                    {{ $productDetail['product']->name }}
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
                                    <th class=" p-1 text-center">Peso Neto (lb)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pallet->boxes as $box)
                                    <tr class="{{ $loop->even ? 'bg-white' : 'bg-gray-50' }}">
                                        <td class=" p-1 font-mono">{{ $box->box->id }}</td>
                                        <td class=" p-1">
                                            {{ $box->box->product->name }}</td>
                                        <td class=" p-1 text-center font-mono text-xs">
                                            {{ $box->box->lot }}</td>
                                        <td class=" p-1 text-center font-mono text-xs">
                                            {{ number_format($box->box->net_weight, 2, ',', '.') }} kg
                                        </td>
                                        <td class=" p-1 text-center font-mono text-xs">
                                            @if ($box->box->is_weighed_in_pounds)
                                                {{ number_format($box->box->net_weight_lb, 2, ',', '.') }} lb
                                            @else
                                                -
                                            @endif
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
