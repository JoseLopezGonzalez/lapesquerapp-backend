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

        /*  table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; } */
    </style>
</head>

<body>
    <div class="pt-4 pl-2 pr-7">
        <div class="grid grid-cols-12" style="margin-bottom: 1rem;">
            <div class="col-span-4">
                <img src="{{ asset(env('DELIVERY_NOTE_LOGO_PATH')) }}" class="h-24" alt="Logo">
            </div>
            <div class="col-span-8" style="line-height: 122%; text-align: right; color: #1E79BB;">
                <p style="font-size: 10pt;">
                    <strong>{{ tenantSetting('company.name') }}</strong><br>
                    C.I.F.: {{ tenantSetting('company.cif') }}<br>
                    {{ tenantSetting('company.address.street') }}<br>
                    {{ tenantSetting('company.address.postal_code') }} {{ tenantSetting('company.address.city') }}
                    ({{ tenantSetting('company.address.province') }})
                </p>
            </div>
        </div>
        <div class="grid grid-cols-12" style=" margin-bottom: 1rem;">
            <!-- Color bars across the top -->
            <div class="col-span-3"
                style=" padding-right: 0; padding-left: 0; height: 0.1rem; background-color: #06d6ff;"></div>
            <div class="col-span-3"
                style=" padding-right: 0; padding-left: 0; height: 0.1rem; background-color: #079def;"></div>
            <div class="col-span-3"
                style=" padding-right: 0; padding-left: 0; height: 0.1rem; background-color: #d1d1d1;"></div>
            <div class="col-span-3"
                style=" padding-right: 0; padding-left: 0; height: 0.1rem; background-color: black;"></div>
        </div>
        <div class="grid grid-cols-12 mt-2 mb-4">
            <div class="col-span-12">
                <p style="margin-top: 1.2rem; font-size: 1.5rem;"><strong>DELIVERY NOTE</strong></p>
            </div>
            <div class="col-span-5 mt-3">
                <table class="w-full  border-none">
                    <tbody>
                        <tr class="border-b-2 border-gray-200 ">
                            <th class="text-left font-medium text-sm p-2">Number</th>
                            <td class="text-left text-sm">{{ $order->formattedId }}</td>
                        </tr>
                        <tr class="border-b border-gray-200">
                            <th class="text-left font-medium text-sm p-2">Date</th>
                            <td class="text-left text-sm"> {{ date('d/m/Y', strtotime($order->load_date)) }} </td>
                        </tr>
                        <tr class="border-b border-gray-200">
                            <th class="text-left font-medium text-sm p-2">Buyer Reference</th>
                            <td class="text-left text-sm">{{ $order->buyer_reference }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="col-span-7 text-right mt-2" style="line-height: 100%;">
                <p class="cliente preserve-line-breaks bold-first-line" style="font-size: 0.9rem;">

                    {{ $order->customer->alias }} <br />
                    {{-- Delete first line of Text of billing Address y respetar los saltos de lineas del texto original
                    con nl2br --}}

                    @php
                        // Separamos el texto en líneas
                        $addressLines = explode("\n", $order->billing_address);
                        // Quitamos la primera línea
                        array_shift($addressLines);
                        // Unimos nuevamente el texto, excluyendo la primera línea
                        $modifiedAddress = implode("\n", $addressLines);
                    @endphp

                    {!! nl2br(e($modifiedAddress)) !!}




                    {{-- {!! nl2br($order->billing_address) !!} --}}

                </p>
            </div>
        </div>
        <div class="w-full mt-12">
            <table class="w-full text-sm">
                <thead class="border-b-2 border-black">
                    <tr>
                        <th class="text-left p-1.5">Item</th>
                        <th class="text-center">Boxes</th>
                        <th class="text-center">Weight</th>
                    </tr>
                </thead>
                <tbody class="border-b-2 border-black">
                    @foreach ($order->productsBySpeciesAndCaptureZone as $productsBySpeciesAndCaptureZone)
                        @foreach ($productsBySpeciesAndCaptureZone['products'] as $product)
                            <tr class="border-b border-gray-200">
                                <th class="text-left font-medium p-1.5">{{ $product['product']->article->name }}</th>
                                <td class="text-center">{{ $product['boxes'] }}</td>
                                <td class="text-center">{{ number_format($product['netWeight'], 2, ',', '.') }} kg</td>
                            </tr>
                        @endforeach

                        <tr class="border-b border-gray-200">
                            {{-- {{dd($order)}}
                            {{dd($productsBySpeciesAndCaptureZone['species'])}} --}}
                            <th class="text-left font-light italic  p-1.5" style="font-size:0.60rem; line-height:100%">
                                {{ $productsBySpeciesAndCaptureZone['species']->scientific_name . '(' . $productsBySpeciesAndCaptureZone['species']->fao . ')' . ' - ' . $productsBySpeciesAndCaptureZone['captureZone']->name . ' - Caught with: ' . $productsBySpeciesAndCaptureZone['species']->fishingGear->name }}
                            </th>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                        </tr>
                    @endforeach
                    {{-- <tr class="border-b border-gray-200">
                        <th class="italic text-left p-1.5 font-normal">Lots:

                            {{ implode(', ', $order->lots)}}
                        </th>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr class="border-b border-black">
                        <th class="text-left p-1.5 font-normal">Pallets: {{ $order->numberOfPallets}}</th>
                        <td></td>
                        <td></td>
                    </tr> --}}

                </tbody>
                <tfoot>
                    <tr class="">
                        <th class="text-left font-medium p-1.5">Total</th>
                        <td class="text-center">{{ $order->totals['boxes'] }}</td>
                        <td class="text-center">{{ number_format($order->totals['netWeight'], 2, ',', '.') }} kg</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="grid grid-cols-2">



            <div class="grid grid-cols-12 mt-8">
                <div class="col-span-10">


                    <div class="col-span-10">
                        <p style="font-size: 1.2rem;"><strong>Delivery Address:</strong></p>
                        <p class="text-sm mt-3 preserve-line-breaks bold-first-line">
                            {!! nl2br($order->shipping_address) !!}
                        </p>

                    </div>

                    <p style="font-size: 1.2rem; margin-top: 1.5rem;"><strong>Terms & Conditions:</strong></p>
                    <p class="mt-3 text-sm">
                        <strong class="mr-1">INCOTERM:</strong> {{ $order->incoterm->code }}
                        ({{ $order->incoterm->description }})
                    </p>


                </div>
            </div>

            <div class="grid grid-cols-12 mt-8">
                <div class="col-span-10">
                    <p class="text-sm mt-1 preserve-line-breaks bold-first-line">
                        <strong>Lots:</strong> {{ implode(', ', $order->lots) }}
                    </p>
                    <p class="mt-1 text-sm">
                        <strong>Pallets:</strong> {{ $order->numberOfPallets }}
                    </p>

                </div>
            </div>

        </div>

    </div>

</body>

</html>