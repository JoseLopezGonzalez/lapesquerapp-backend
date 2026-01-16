<!-- resources/views/pdf/delivery_note.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <title>Delivery Note Pesca</title>
    {{-- Tailwind no funciona, lo cojo todo directamente de un cdn --}}

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'DejaVu Sans';
        }

        .bold-first-line::first-line {
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="pt-4 pl-2 pr-7">
        <div class="grid grid-cols-12" style="margin-bottom: 1rem;">
            <div class="col-span-6  ">
                <p style="margin-top: 1.2rem; font-size: 1.5rem;"><strong>ALBARÁN {{ $order->formattedId }}</strong></p>
                <table class="w-full  border-none mt-5">
                    <tbody>
                        <tr class="border-b border-gray-200">
                            <th class="text-left font-medium text-sm p-2">Fecha</th>
                            <td class="text-left text-sm"> {{ date('d/m/Y', strtotime($order->load_date)) }} </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="col-span-6" style="line-height: 122%; text-align: right; color: green;">
                <p style="font-size: 10pt;">
                    <strong>LA PESCA DEL MERIDION, S.L.U.</strong><br>
                    PATIO DEL PENINSULAR, 19</br>
                    21409 - AYAMONTE</br>
                    HUELVA - ESPAÑA
                </p>
            </div>
        </div>

        <div class="grid grid-cols-12 mt-10 mb-4">
            <div class="col-span-5 mt-3">
                <h1 class="text-2xl font-bold">Cliente:</h1>
            </div>
            <div class="col-span-7 text-right mt-2" style="line-height: 100%;">
                <p class="cliente preserve-line-breaks bold-first-line" style="font-size: 0.9rem;">
                    {!! nl2br($order->billing_address) !!}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-12" style=" margin-bottom: 1rem;">
            <div class="col-span-3"
                style=" padding-right: 0; padding-left: 0; height: 0.1rem; background-color: black;"></div>
            <div class="col-span-3"
                style=" padding-right: 0; padding-left: 0; height: 0.1rem; background-color: black;"></div>
            <div class="col-span-3"
                style=" padding-right: 0; padding-left: 0; height: 0.1rem; background-color: black;"></div>
            <div class="col-span-3"
                style=" padding-right: 0; padding-left: 0; height: 0.1rem; background-color: black;"></div>
        </div>
        <div class="w-full mt-20">
            <table class="w-full text-sm">
                <thead class="border-b-2 border-black">
                    <tr>
                        <th class="text-left p-1.5">Articulo</th>
                        <th class="text-center">Cajas</th>
                        <th class="text-center">Cantidad</th>
                    </tr>
                </thead>
                <tbody class="border-b-2 border-black">
                    @foreach ($order->productsBySpeciesAndCaptureZone as $productsBySpeciesAndCaptureZone)
                        @foreach ($productsBySpeciesAndCaptureZone['products'] as $product)
                            <tr class="border-b border-gray-200">
                                <th class="text-left font-medium p-1.5">{{ $product['product']->name }}</th>
                                <td class="text-center">{{ $product['boxes'] }}</td>
                                <td class="text-center">{{ number_format($product['netWeight'], 2, ',', '.') }} kg</td>
                            </tr>
                        @endforeach
                        <tr class="border-b border-gray-200">
                            <th class="text-left font-light italic  p-1.5" style="font-size:0.60rem; line-height:100%">
                                {{ $productsBySpeciesAndCaptureZone['species']->scientific_name . '(' . $productsBySpeciesAndCaptureZone['species']->fao . ')' }}
                            </th>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                        </tr>
                    @endforeach
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
    </div>
</body>

</html>
