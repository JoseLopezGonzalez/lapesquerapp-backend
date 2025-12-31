<!DOCTYPE html>
<html>

<head>
    <title>Liquidación de Proveedor</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'DejaVu Sans';
        }

        @page {
            size: A4 portrait;
        }

        .bold-first-line::first-line {
            font-weight: bold;
        }

        .break-before-page {
            page-break-before: always;
        }
    </style>
</head>

<body class="bg-white text-black text-xs">
    <div class="max-w-[210mm] mx-auto p-6 bg-white rounded min-h-screen">
        <!-- ENCABEZADO -->
        <div class="flex justify-between items-end mb-6">
            <div class="flex items-center gap-2">
                <div>
                    <h1 class="text-md font-bold">{{ tenantSetting('company.name') }}</h1>
                    <p>{{ tenantSetting('company.address.street') }} - {{ tenantSetting('company.address.postal_code') }}
                        {{ tenantSetting('company.address.city') }}
                    </p>
                    <p>Tel: {{ tenantSetting('company.contact.phone_admin') }}</p>
                    <p>{{ tenantSetting('company.contact.email_admin') }}</p>
                    <p>{{ tenantSetting('company.sanitary_number') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-4">
                <div class="rounded text-end">
                    <h2 class="text-lg font-bold">LIQUIDACIÓN DE PROVEEDOR</h2>
                    <p class="font-medium">Proveedor: <span class="">{{ $supplier['name'] }}</span></p>
                    <p class="font-medium">Período: 
                        <span class="">{{ date('d/m/Y', strtotime($date_range['start'])) }}</span> - 
                        <span class="">{{ date('d/m/Y', strtotime($date_range['end'])) }}</span>
                    </p>
                    <p class="font-medium">Fecha de generación: <span class="">{{ date('d/m/Y H:i') }}</span></p>
                </div>
            </div>
        </div>

        <!-- DATOS DEL PROVEEDOR -->
        <div class="border rounded-lg overflow-hidden bg-gray-50 mb-6">
            <div class="font-bold p-2 bg-gray-800 w-full border-b text-white">DATOS DEL PROVEEDOR</div>
            <div class="p-4">
                <p><strong>Nombre:</strong> {{ $supplier['name'] }}</p>
                @if($supplier['contact_person'])
                    <p><strong>Persona de contacto:</strong> {{ $supplier['contact_person'] }}</p>
                @endif
                @if($supplier['phone'])
                    <p><strong>Teléfono:</strong> {{ $supplier['phone'] }}</p>
                @endif
                @if($supplier['address'])
                    <p><strong>Dirección:</strong> {{ $supplier['address'] }}</p>
                @endif
            </div>
        </div>

        <!-- TABLA DE RECEPCIONES Y SALIDAS -->
        <h3 class="font-bold mb-2">DETALLE DE RECEPCIONES Y SALIDAS DE CEBO</h3>
        <div class="border rounded-lg overflow-hidden">
            <table class="w-full text-xs">
                <thead class="border-b bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Fecha</th>
                        <th class="p-2 text-left">Tipo</th>
                        <th class="p-2 text-left">Producto</th>
                        <th class="p-2 text-center">Lote</th>
                        <th class="p-2 text-center">Peso Neto (kg)</th>
                        <th class="p-2 text-center">Precio (€/kg)</th>
                        <th class="p-2 text-center">Importe (€)</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $rowIndex = 0;
                    @endphp

                    @foreach ($items as $item)
                        @if($item['type'] === 'reception')
                            @php
                                $reception = $item['reception'];
                                $hasRelatedDispatches = !empty($item['related_dispatches']);
                            @endphp

                            <!-- Recepción -->
                            @foreach ($reception['products'] as $product)
                                @php
                                    $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                    $rowIndex++;
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td class="p-2 py-1">{{ date('d/m/Y', strtotime($reception['date'])) }}</td>
                                    <td class="p-2 py-1 font-semibold">RECEPCIÓN</td>
                                    <td class="p-2 py-1">{{ $product['product']['name'] ?? 'N/A' }}</td>
                                    <td class="p-2 py-1 text-center">{{ $product['lot'] ?? '-' }}</td>
                                    <td class="p-2 py-1 text-center">{{ number_format($product['net_weight'], 2, ',', '.') }}</td>
                                    <td class="p-2 py-1 text-center">{{ number_format($product['price'], 2, ',', '.') }}</td>
                                    <td class="p-2 py-1 text-center">{{ number_format($product['amount'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach

                            <!-- Totales de la recepción -->
                            @php
                                $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                $rowIndex++;
                            @endphp
                            <tr class="{{ $rowClass }} font-semibold bg-blue-50">
                                <td class="p-2 py-1" colspan="3">Total Recepción #{{ $reception['id'] }} (Calculado)</td>
                                <td class="p-2 py-1 text-center">-</td>
                                <td class="p-2 py-1 text-center">{{ number_format($reception['calculated_total_net_weight'], 2, ',', '.') }}</td>
                                <td class="p-2 py-1 text-center">{{ number_format($reception['average_price'], 2, ',', '.') }}</td>
                                <td class="p-2 py-1 text-center">{{ number_format($reception['calculated_total_amount'], 2, ',', '.') }}</td>
                            </tr>

                            @if($reception['declared_total_net_weight'] > 0 || $reception['declared_total_amount'] > 0)
                                @php
                                    $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                    $rowIndex++;
                                @endphp
                                <tr class="{{ $rowClass }} font-semibold bg-yellow-50">
                                    <td class="p-2 py-1" colspan="3">Total Recepción #{{ $reception['id'] }} (Declarado)</td>
                                    <td class="p-2 py-1 text-center">-</td>
                                    <td class="p-2 py-1 text-center">{{ number_format($reception['declared_total_net_weight'], 2, ',', '.') }}</td>
                                    <td class="p-2 py-1 text-center">-</td>
                                    <td class="p-2 py-1 text-center">{{ number_format($reception['declared_total_amount'], 2, ',', '.') }}</td>
                                </tr>
                            @endif

                            <!-- Salidas de cebo relacionadas -->
                            @if($hasRelatedDispatches)
                                @foreach ($item['related_dispatches'] as $dispatch)
                                    @php
                                        $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                        $rowIndex++;
                                    @endphp
                                    <tr class="{{ $rowClass }} bg-red-50">
                                        <td class="p-2 py-1" colspan="7" class="font-semibold">
                                            ↪ Salida de Cebo #{{ $dispatch['id'] }} - {{ date('d/m/Y', strtotime($dispatch['date'])) }}
                                        </td>
                                    </tr>
                                    @foreach ($dispatch['products'] as $product)
                                        @php
                                            $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                            $rowIndex++;
                                        @endphp
                                        <tr class="{{ $rowClass }} bg-red-50">
                                            <td class="p-2 py-1">{{ date('d/m/Y', strtotime($dispatch['date'])) }}</td>
                                            <td class="p-2 py-1 font-semibold">SALIDA CEBO</td>
                                            <td class="p-2 py-1">{{ $product['product']['name'] ?? 'N/A' }}</td>
                                            <td class="p-2 py-1 text-center">-</td>
                                            <td class="p-2 py-1 text-center">{{ number_format($product['net_weight'], 2, ',', '.') }}</td>
                                            <td class="p-2 py-1 text-center">{{ number_format($product['price'], 2, ',', '.') }}</td>
                                            <td class="p-2 py-1 text-center">{{ number_format($product['amount'], 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                    @php
                                        $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                        $rowIndex++;
                                    @endphp
                                    <tr class="{{ $rowClass }} font-semibold bg-red-100">
                                        <td class="p-2 py-1" colspan="4">Total Salida #{{ $dispatch['id'] }}</td>
                                        <td class="p-2 py-1 text-center">{{ number_format($dispatch['total_net_weight'], 2, ',', '.') }}</td>
                                        <td class="p-2 py-1 text-center">-</td>
                                        <td class="p-2 py-1 text-center">{{ number_format($dispatch['total_amount'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            @endif

                        @elseif($item['type'] === 'dispatch')
                            @php
                                $dispatch = $item['dispatch'];
                            @endphp

                            <!-- Salida de cebo sin recepción relacionada -->
                            @php
                                $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                $rowIndex++;
                            @endphp
                            <tr class="{{ $rowClass }} bg-orange-50">
                                <td class="p-2 py-1" colspan="7" class="font-semibold">
                                    Salida de Cebo #{{ $dispatch['id'] }} - {{ date('d/m/Y', strtotime($dispatch['date'])) }} (Sin recepción relacionada)
                                </td>
                            </tr>
                            @foreach ($dispatch['products'] as $product)
                                @php
                                    $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                    $rowIndex++;
                                @endphp
                                <tr class="{{ $rowClass }} bg-orange-50">
                                    <td class="p-2 py-1">{{ date('d/m/Y', strtotime($dispatch['date'])) }}</td>
                                    <td class="p-2 py-1 font-semibold">SALIDA CEBO</td>
                                    <td class="p-2 py-1">{{ $product['product']['name'] ?? 'N/A' }}</td>
                                    <td class="p-2 py-1 text-center">-</td>
                                    <td class="p-2 py-1 text-center">{{ number_format($product['net_weight'], 2, ',', '.') }}</td>
                                    <td class="p-2 py-1 text-center">{{ number_format($product['price'], 2, ',', '.') }}</td>
                                    <td class="p-2 py-1 text-center">{{ number_format($product['amount'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            @php
                                $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                $rowIndex++;
                            @endphp
                            <tr class="{{ $rowClass }} font-semibold bg-orange-100">
                                <td class="p-2 py-1" colspan="4">Total Salida #{{ $dispatch['id'] }}</td>
                                <td class="p-2 py-1 text-center">{{ number_format($dispatch['total_net_weight'], 2, ',', '.') }}</td>
                                <td class="p-2 py-1 text-center">-</td>
                                <td class="p-2 py-1 text-center">{{ number_format($dispatch['total_amount'], 2, ',', '.') }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- RESUMEN FINAL -->
        <div class="mt-6 border rounded-lg overflow-hidden bg-gray-50">
            <div class="font-bold p-2 bg-gray-800 w-full border-b text-white">RESUMEN GLOBAL</div>
            <div class="p-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-bold mb-2">RECEPCIONES</h4>
                        <p><strong>Cantidad:</strong> {{ $summary['total_receptions'] }}</p>
                        <p><strong>Peso Total:</strong> {{ number_format($summary['total_receptions_weight'], 2, ',', '.') }} kg</p>
                        <p><strong>Importe Total:</strong> {{ number_format($summary['total_receptions_amount'], 2, ',', '.') }} €</p>
                    </div>
                    <div>
                        <h4 class="font-bold mb-2">SALIDAS DE CEBO</h4>
                        <p><strong>Cantidad:</strong> {{ $summary['total_dispatches'] }}</p>
                        <p><strong>Peso Total:</strong> {{ number_format($summary['total_dispatches_weight'], 2, ',', '.') }} kg</p>
                        <p><strong>Importe Total:</strong> {{ number_format($summary['total_dispatches_amount'], 2, ',', '.') }} €</p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t">
                    <h4 class="font-bold mb-2">TOTALES DECLARADOS</h4>
                    <p><strong>Peso Total Declarado:</strong> {{ number_format($summary['total_declared_weight'], 2, ',', '.') }} kg</p>
                    <p><strong>Importe Total Declarado:</strong> {{ number_format($summary['total_declared_amount'], 2, ',', '.') }} €</p>
                </div>
                <div class="mt-4 pt-4 border-t">
                    <h4 class="font-bold text-lg">IMPORTE NETO: {{ number_format($summary['net_amount'], 2, ',', '.') }} €</h4>
                    <p class="text-xs text-gray-600">(Recepciones - Salidas de Cebo)</p>
                </div>
            </div>
        </div>

    </div>
</body>

</html>

