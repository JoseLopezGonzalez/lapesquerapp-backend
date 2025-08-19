<!-- resources/views/pdf/delivery_note.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <title>Delivery Note </title>
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
            size: landscape;
            /* margin: 10mm 30mm 10mm 10mm;  */
            /* top, right, bottom, left */
        }

        /*  table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; } */
    </style>
</head>

<body>
    @foreach ($order->pallets as $pallet)
        <div class="w-full p-4 text-black h-svh flex flex-col gap-3 bg-white border-black rounded-lg">
            <div class='grid grid-cols-2 w-full gap-3'>
                <div class="space-y-2 border rounded-lg p-8">
                    <div class="text-md font-semibold">Expedidor:</div>
                    <div class="text-2xl font-semibold">{{ tenantSetting('company.name') }}</div>
                    <p>
                        {{ tenantSetting('company.address.street') }}<br>
                        {{ tenantSetting('company.address.postal_code') }} {{ tenantSetting('company.address.city') }}
                        ({{ tenantSetting('company.address.province') }})<br>
                        {{ tenantSetting('company.address.country') }}
                    </p>
                </div>


                <div class="space-y-2 border rounded-lg p-8">
                    <div class="text-md font-semibold ">Consignatario:</div>
                    <div class="text-2xl font-semibold ">{{ $order->customer->alias }}</div>
                    <p class="">
                        {{-- Delete first line of Text of shipping Address y respetar los saltos de lineas del texto
                        original con nl2br --}}

                        @php
                            // Separamos el texto en líneas
                            $addressLines = explode("\n", $order->shipping_address);
                            // Quitamos la primera línea
                            array_shift($addressLines);
                            // Unimos nuevamente el texto, excluyendo la primera línea
                            $modifiedAddress = implode("\n", $addressLines);
                        @endphp

                        {!! nl2br(e($modifiedAddress)) !!}
                    </p>
                </div>
            </div>


            <div class="grid grid-cols-3 gap-4 bg-gray-50 p-4 py-8 rounded-lg border w-full">
                <div class="text-center">
                    <div class="text-3xl font-bold ">{{ $pallet->id }}</div>
                    <div class="text-sm font-medium ">Nº PALET</div>
                </div>
                <div class="text-center border-r border-l">
                    <div class="text-3xl font-bold ">{{ $pallet->numberOfBoxes }}</div>
                    <div class="text-sm font-medium ">CAJAS</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold ">
                        {{ number_format($pallet->netWeight, 2, ',', '.') }} kg
                    </div>
                    <div class="text-sm font-medium ">PESO NETO</div>
                </div>
            </div>

            <div class='w-full grid grid-cols-3 items-center flex-1 gap-3'>
                {{-- <div class="border rounded-lg h-full p-4">
                    <div class="flex items-center gap-1 p-2 bg-white">
                        <div class='flex flex-col items-start '>
                            <div class="font-semibold">Gamba Argentina</div>
                            <div class="flex items-center justify-start gap-2 text-sm">
                                <div>- 10 cajas</div>
                                <div>- GTIN: 32456436435</div>
                            </div>
                        </div>
                    </div>
                </div> --}}
                <div class="flex flex-col items-center justify-center border rounded-lg h-full gap-2">
                    <h1 class="text-4xl font-semibold ">{{ $order->formattedId }}</h1>
                    <span class="text-md">Pedido</span>
                </div>
                <div class="flex flex-col items-center justify-center border rounded-lg h-full p-14 gap-2">
                    <img alt='Barcode Generator TEC-IT'
                        src="{{ 'https://barcode.tec-it.com/barcode.ashx?data=Pedido%3A' . $pallet->id . '&code=QRCode&eclevel=L' }}"
                        class='h-full' />
                    <span class="text-xs">Palet: {{ $pallet->id }}</span>
                </div>
                <div class="flex flex-col items-center justify-center border rounded-lg h-full p-14 gap-2">
                    <img alt='Barcode Generator TEC-IT'
                        src="{{ 'https://barcode.tec-it.com/barcode.ashx?data=Pedido%3A' . $order->id . '&code=QRCode&eclevel=L' }}"
                        class='h-full' />
                    <span class="text-xs">Pedido: {{ $order->id }}</span>
                </div>



            </div>

            <div class="text-3xl font-semibold  border-l-4 border-gray-300 pl-3 mt-3">
                {{ $order->transport->name }}
            </div>




        </div>
    @endforeach


</body>

</html>