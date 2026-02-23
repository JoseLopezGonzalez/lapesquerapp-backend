<!-- resources/views/pdf/delivery_note.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <title>CMR</title>
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

            margin: 1mm 1mm 1mm 1mm;
            /* top, right, bottom, left */
        }

        /*  table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; } */
    </style>
</head>

<body>



    @php
        $imgs = ['cmr-page-1.png', 'cmr-page-2.png', 'cmr-page-3.png', 'cmr-page-4.png'];
    @endphp




    <div>
        @foreach ($imgs as $index => $img)
            <div class="imprimir  h-screen ">
                <div class="text-center text-uppercase h-full" style="position: relative;">
                    <div class=" flex items-center justify-center h-full overflow-hidden w-full"
                        style="position: absolute;  ">
                        <img src="{{ asset('images/documents/CMR/' . $img) }}" class="h-full" />

                    </div>
                    <p style="text-align: left; font-size: 9pt; left: 90px; top: 70px; position: absolute;">
                        {{ tenantSetting('company.name') }}<br>
                        {{ tenantSetting('company.address.street') }}<br>
                        {{ tenantSetting('company.address.postal_code') }} {{ tenantSetting('company.address.city') }}<br>
                        {{ tenantSetting('company.address.province') }} - {{ tenantSetting('company.address.country') }}
                    </p>
                    <p
                        style="font-weight: bold; text-align: left; font-size: 9pt; left: 655px; top: 50px; position: absolute;">
                        {{ $order->formattedId }}
                    </p>


                    @if ($order->customer->country->name === 'España')
                        <p
                            style="font-weight: bold; text-align: left; font-size: 9pt; left: 563px; top: 24px; position: absolute;">
                            X
                        </p>
                    @else
                        <p
                            style="font-weight: bold; text-align: left; font-size: 9pt; left: 423px; top: 24px; position: absolute;">
                            X
                        </p>
                    @endif

                    {{-- img --}}
                    @php
                        $currentTenant = app('currentTenant');
                        $sealPath = "images/documents/CMR/sello-{$currentTenant}.png";
                        $sealExists = file_exists(public_path($sealPath));
                    @endphp
                    @if ($sealExists)
                        <img src="{{ asset($sealPath) }}"
                            style="position: absolute; left: 80px; top: 923px; width: 190px; " />
                    @endif




                    <p class="preserve-line-breaks"
                        style="text-align: left; font-size: 8pt; left: 420px; top: 168px; position: absolute;">
                        {{ $order->transport->name }} <br />
                        {!! nl2br(e($order->transport->address)) !!}

                    </p>
                    <p class="preserve-line-breaks"
                        style="text-align: left; font-size: 7pt; left: 90px; top: 163px; position: absolute;">
                        {{ $order->customer->alias }} <br />
                        @php
                            // Separamos el texto en líneas
                            $addressLines = explode("\n", $order->billing_address);
                            // Quitamos la primera línea
                            array_shift($addressLines);
                            // Unimos nuevamente el texto, excluyendo la primera línea
                            $modifiedAddress = implode("\n", $addressLines);
                        @endphp

                        {!! nl2br(e($modifiedAddress)) !!}

                    </p>
                    <p class="preserve-line-breaks"
                        style="text-align: left; font-size: 6pt; left: 90px; top: 253px; position: absolute;">
                        {{-- {{ $order->customer->alias }} <br /> --}}

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
                    <p style="text-align: left; font-size: 9pt; left: 90px; top: 330px; position: absolute;">
                        {{ tenantSetting('company.loading_place') }}
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 320px; top: 302px; position: absolute;">
                        {{ date('d/m/Y', strtotime($order->load_date)) }}
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 90px; top: 390px; position: absolute;">
                        ALBARÁN {{ $order->formattedId }}
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 90px; top: 450px; position: absolute;">
                        {{ $order->numberOfPallets }} palets
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 200px; top: 450px; position: absolute;">
                        {{ $order->totals['boxes'] }}
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 280px; top: 450px; position: absolute;">
                        cajas
                    </p>
                    <p
                        style="font-size:5pt; text-align: left; font-size: 9pt; left: 380px; top: 450px; position: absolute;">
                        produtos de <br /> la pesca
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 580px; top: 450px; position: absolute;">
                        {{ number_format($order->totals['netWeight'], 2) }} kg
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 190px; top: 680px; position: absolute;">
                        0 ºC
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 160px; top: 855px; position: absolute;">
                        {{ tenantSetting('company.signature_location') }}
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 290px; top: 855px; position: absolute;">
                        {{ date('d/m/Y', strtotime($order->load_date)) }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>



</body>

</html>