<!DOCTYPE html>
<html>

<head>
    <title>Letreros de Transporte (Restringidos)</title>
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
    </style>
</head>

<body>
    @foreach ($entity->pallets as $pallet)
        <div class="w-full p-4 text-black h-svh flex flex-col gap-3 bg-white border-black rounded-lg">
            <div class='grid grid-cols-1 w-full gap-3'>
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
                <div class="flex flex-col items-center justify-center border rounded-lg h-full gap-2">
                    <h1 class="text-4xl font-semibold ">{{ $entity->formattedId }}</h1>
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
                        src="{{ 'https://barcode.tec-it.com/barcode.ashx?data=Pedido%3A' . $entity->id . '&code=QRCode&eclevel=L' }}"
                        class='h-full' />
                    <span class="text-xs">Pedido: {{ $entity->id }}</span>
                </div>
            </div>
        </div>
    @endforeach
</body>

</html>

