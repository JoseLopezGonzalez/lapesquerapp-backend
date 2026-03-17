<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Oferta {{ $offer->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1, h2, h3 { margin: 0 0 12px; }
        .muted { color: #6b7280; }
        .section { margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <div class="section">
        <h1>Oferta #{{ $offer->id }}</h1>
        <p class="muted">Estado: {{ $offer->status }}</p>
        <p>Destinatario: {{ $offer->prospect?->company_name ?? $offer->customer?->name }}</p>
        <p>Comercial: {{ $offer->salesperson?->name }}</p>
        <p>Válida hasta: {{ $offer->valid_until?->format('Y-m-d') ?? 'Sin definir' }}</p>
    </div>

    <div class="section">
        <h2>Condiciones</h2>
        <p>Incoterm: {{ $offer->incoterm?->code ?? 'N/D' }}</p>
        <p>Forma de pago: {{ $offer->paymentTerm?->name ?? 'N/D' }}</p>
        <p>Moneda: {{ $offer->currency }}</p>
        <p>Notas: {{ $offer->notes ?? 'Sin notas' }}</p>
    </div>

    <div class="section">
        <h2>Líneas</h2>
        <table>
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Cantidad</th>
                    <th>Unidad</th>
                    <th>Precio</th>
                    <th>Impuesto</th>
                    <th>Cajas</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($offer->lines as $line)
                    <tr>
                        <td>{{ $line->description }}</td>
                        <td>{{ number_format($line->quantity, 3, ',', '.') }}</td>
                        <td>{{ $line->unit }}</td>
                        <td>{{ number_format($line->unit_price, 4, ',', '.') }} {{ $line->currency }}</td>
                        <td>{{ $line->tax?->name ?? 'N/D' }}</td>
                        <td>{{ $line->boxes ?? 'N/D' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
