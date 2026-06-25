<!DOCTYPE html>
<html>

<head>
    <title>CMR Maquilador</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'DejaVu Sans';
        }

        @page {
            margin: 1mm 1mm 1mm 1mm;
        }
    </style>
</head>

<body>

    @php
        $imgs = ['cmr-page-1.png', 'cmr-page-2.png', 'cmr-page-3.png', 'cmr-page-4.png'];

        $destination = $entity->maquilador_destination ?? 'Cliente #' . $entity->customer_id;

        $externalProcessor = $entity->externalProcessor;

        // Lugar de carga: campo del pedido > ciudad del maquilador > settings empresa
        $loadingPlace = $entity->loading_address
            ?? ($externalProcessor
                ? trim(($externalProcessor->city ?? '') . ($externalProcessor->province ? ' - ' . $externalProcessor->province : ''))
                : null)
            ?? (tenantSetting('company.address.city') . ' - ' . tenantSetting('company.address.province'));

        // Ciudad de firma: siempre la empresa emisora
        $signingCity = tenantSetting('company.address.city');

        // España o internacional según el maquilador; si no hay país, España por defecto
        $isSpain = $externalProcessor?->country?->name === 'España'
            || $externalProcessor?->country === null;
    @endphp

    <div>
        @foreach ($imgs as $index => $img)
            <div class="imprimir h-screen">
                <div class="text-center text-uppercase h-full" style="position: relative;">
                    <div class="flex items-center justify-center h-full overflow-hidden w-full"
                        style="position: absolute;">
                        <img src="{{ asset('images/documents/CMR/' . $img) }}" class="h-full" />
                    </div>

                    {{-- Expedidor (casilla 1) --}}
                    <p style="text-align: left; font-size: 9pt; left: 90px; top: 70px; position: absolute;">
                        {{ tenantSetting('company.name') }}<br>
                        {{ tenantSetting('company.address.street') }}<br>
                        {{ tenantSetting('company.address.postal_code') }} {{ tenantSetting('company.address.city') }}<br>
                        {{ tenantSetting('company.address.province') }} - {{ tenantSetting('company.address.country') }}
                    </p>

                    {{-- Número de pedido --}}
                    <p style="font-weight: bold; text-align: left; font-size: 9pt; left: 655px; top: 50px; position: absolute;">
                        {{ $entity->formattedId }}
                    </p>

                    {{-- España o internacional --}}
                    @if ($isSpain)
                        <p style="font-weight: bold; text-align: left; font-size: 9pt; left: 563px; top: 24px; position: absolute;">
                            X
                        </p>
                    @else
                        <p style="font-weight: bold; text-align: left; font-size: 9pt; left: 423px; top: 24px; position: absolute;">
                            X
                        </p>
                    @endif

                    {{-- Sello empresa --}}
                    @php
                        $currentTenant = app('currentTenant');
                        $sealPath = "images/documents/CMR/sello-{$currentTenant}.png";
                        $sealExists = file_exists(public_path($sealPath));
                    @endphp
                    @if ($sealExists)
                        <img src="{{ asset($sealPath) }}"
                            style="position: absolute; left: 80px; top: 923px; width: 190px;" />
                    @endif

                    {{-- Transportista (casilla 16) --}}
                    <p class="preserve-line-breaks"
                        style="text-align: left; font-size: 8pt; left: 420px; top: 168px; position: absolute;">
                        {{ $entity->transport?->name }}<br />
                        {!! nl2br(e($entity->transport?->address)) !!}
                    </p>

                    {{-- Consignatario (casilla 2): destino anonimizado --}}
                    <p class="preserve-line-breaks"
                        style="text-align: left; font-size: 7pt; left: 90px; top: 163px; position: absolute;">
                        {{ $destination }}
                    </p>

                    {{-- Matrículas --}}
                    <p style="text-align: left; font-size: 9pt; left: 550px; top: 350px; position: absolute;">
                        {{ $entity->truck_plate }}
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 635px; top: 350px; position: absolute;">
                        {{ $entity->trailer_plate }}
                    </p>

                    {{-- Destinatario / lugar de entrega (casilla 3): destino anonimizado --}}
                    <p class="preserve-line-breaks"
                        style="text-align: left; font-size: 6pt; left: 90px; top: 253px; position: absolute;">
                        {{ $destination }}
                    </p>

                    {{-- Lugar de carga (casilla 4): dinámico --}}
                    <p style="text-align: left; font-size: 9pt; left: 90px; top: 330px; position: absolute;">
                        {{ $loadingPlace }}
                    </p>

                    {{-- Fecha de carga --}}
                    <p style="text-align: left; font-size: 9pt; left: 320px; top: 302px; position: absolute;">
                        {{ date('d/m/Y', strtotime($entity->load_date)) }}
                    </p>

                    {{-- Referencia de documento --}}
                    <p style="text-align: left; font-size: 9pt; left: 90px; top: 390px; position: absolute;">
                        Nota de carga {{ $entity->formattedId }}
                    </p>

                    {{-- Mercancía --}}
                    <p style="text-align: left; font-size: 9pt; left: 90px; top: 450px; position: absolute;">
                        {{ $entity->numberOfPallets }} palets
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 200px; top: 450px; position: absolute;">
                        {{ $entity->totals['boxes'] }}
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 280px; top: 450px; position: absolute;">
                        cajas
                    </p>
                    <p style="font-size: 9pt; text-align: left; left: 380px; top: 450px; position: absolute;">
                        productos de <br /> la pesca
                    </p>
                    <p style="text-align: right; font-size: 9pt; right: 165px; top: 450px; position: absolute;">
                        {{ number_format($entity->totals['netWeight'], 2, ',', '.') }} kg
                    </p>

                    {{-- Temperatura --}}
                    <p style="text-align: left; font-size: 9pt; left: 190px; top: 680px; position: absolute;">
                        {{ $entity->temperature ?? '0' }} ºC
                    </p>

                    {{-- Incoterm --}}
                    <p style="text-align: left; font-size: 9pt; left: 90px; top: 815px; position: absolute;">
                        {{ $entity->incoterm?->code }} - {{ $entity->incoterm?->description }}
                    </p>

                    {{-- Lugar y fecha de emisión (casilla 23) --}}
                    <p style="text-align: left; font-size: 9pt; left: 160px; top: 855px; position: absolute;">
                        {{ $signingCity }}
                    </p>
                    <p style="text-align: left; font-size: 9pt; left: 290px; top: 855px; position: absolute;">
                        {{ date('d/m/Y', strtotime($entity->load_date)) }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>

</body>

</html>
