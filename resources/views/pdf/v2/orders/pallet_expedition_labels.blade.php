<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Etiquetas expedición palets</title>
    <style>
        @page {
            size: 110mm 90mm;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            background: #fff;
        }

        .label {
            width: 110mm;
            height: 90mm;
            page-break-after: always;
            padding: 5mm;
            display: flex;
            flex-direction: column;
            gap: 3mm;
            overflow: hidden;
        }

        .label:last-child {
            page-break-after: auto;
        }

        .top {
            display: grid;
            grid-template-columns: 1fr 24mm;
            gap: 4mm;
            align-items: start;
        }

        .company {
            font-size: 10pt;
            font-weight: 700;
            line-height: 1.05;
            text-transform: uppercase;
        }

        .qr {
            width: 24mm;
            height: 24mm;
            object-fit: contain;
        }

        .pallet {
            border-top: 0.7mm solid #111;
            border-bottom: 0.7mm solid #111;
            padding: 2mm 0 1.5mm;
        }

        .pallet-number {
            font-size: 34pt;
            line-height: 0.95;
            font-weight: 900;
            letter-spacing: 0;
            white-space: nowrap;
        }

        .details {
            display: grid;
            grid-template-columns: 24mm 1fr;
            row-gap: 1.6mm;
            column-gap: 2mm;
            font-size: 12pt;
            line-height: 1.12;
        }

        .label-key {
            font-size: 8pt;
            font-weight: 700;
            color: #444;
            text-transform: uppercase;
        }

        .label-value {
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .totals {
            margin-top: auto;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            border: 0.55mm solid #111;
        }

        .total {
            padding: 2.5mm 2mm;
            text-align: center;
        }

        .total + .total {
            border-left: 0.55mm solid #111;
        }

        .total-value {
            display: block;
            font-size: 18pt;
            line-height: 1;
            font-weight: 900;
            white-space: nowrap;
        }

        .total-label {
            display: block;
            margin-top: 1mm;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        .empty {
            width: 110mm;
            height: 90mm;
            padding: 8mm;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 14pt;
            font-weight: 700;
        }
    </style>
</head>

<body>
    @forelse ($labels as $label)
        <section class="label">
            <header class="top">
                <div class="company">{{ $label['companyName'] }}</div>
                <img class="qr" src="{{ $label['qrUrl'] }}" alt="QR palet {{ $label['palletId'] }}">
            </header>

            <main class="pallet">
                <div class="pallet-number">PALET #{{ $label['palletId'] }}</div>
            </main>

            <div class="details">
                <div class="label-key">Pedido</div>
                <div class="label-value">{{ $label['orderFormattedId'] }}</div>

                <div class="label-key">Cliente</div>
                <div class="label-value">{{ $label['customerDestination'] }}</div>

                <div class="label-key">Transp.</div>
                <div class="label-value">{{ $label['transportName'] }}</div>
            </div>

            <footer class="totals">
                <div class="total">
                    <span class="total-value">{{ $label['boxesCount'] }}</span>
                    <span class="total-label">Cajas</span>
                </div>
                <div class="total">
                    <span class="total-value">{{ number_format($label['netWeight'], 2, ',', '.') }} kg</span>
                    <span class="total-label">Peso neto</span>
                </div>
                <div class="total">
                    <span class="total-value">{{ $label['palletTareWeightKg'] !== null ? number_format($label['palletTareWeightKg'], 3, ',', '.') . ' kg' : '—' }}</span>
                    <span class="total-label">Tara madera</span>
                </div>
            </footer>
        </section>
    @empty
        <section class="empty">
            Este pedido no tiene palets asignados.
        </section>
    @endforelse
</body>

</html>
