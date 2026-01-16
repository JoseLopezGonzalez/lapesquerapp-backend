<!-- resources/views/pdf/delivery_note.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <title>Salida de Cebo</title>
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

            margin: 20mm 1mm 20mm 1mm;
            /* top, right, bottom, left */
        }

        /*  table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; } */
    </style>
</head>

<body class="px-20 flex flex-col gap-20">


    @foreach($ceboDispatches as $ceboDispatch)

    <div class="gap-4 flex flex-col items-start justify-center text-black w-full break-inside-avoid">
        <div>
            <p class="font-bold text-3xl">NOTA DE SALIDA</p>
        </div>
    
        <div class="w-full">
            <table class="text-start w-full">
                <tbody class="text-xl w-full">
                    <tr>
                        <td class="text-start"><span class="font-bold">Numero: </span>#{{ $ceboDispatch->id }}</td>
                    </tr>
                    <tr>
                        <td class="text-start"><span class="font-bold">Proveedor: </span>{{ $ceboDispatch->supplier->name }}</td>
                    </tr>
                    <tr>
                        <td class="text-start"><span class="font-bold">Fecha: </span>
                            {{ \Carbon\Carbon::parse($ceboDispatch->date)->format('d/m/Y') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    
        <table class="w-full">
            <thead>
                <tr>
                    <th scope="col" class="text-start">Artículo</th>
                    <th scope="col" class="text-end">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ceboDispatch->products as $product)
                    <tr>
                        <td>{{ $product->product->name }}</td>
                        <td class="text-end">{{ $product->net_weight }} kg</td>
                    </tr>
                @endforeach
                <!-- Total -->
                <tr>
                    <td class="font-bold">Total</td>
                    <td class="text-end font-bold">{{ number_format($ceboDispatch->netWeight, 2) }} kg</td>
                </tr>
            </tbody>
        </table>
    
        <!-- Notes -->
        <div class="w-full">
            <p><span class="font-bold">Notas:</span> {{ $ceboDispatch->notes }}</p>
        </div>
    </div>

    @endforeach


</body>

</html>
