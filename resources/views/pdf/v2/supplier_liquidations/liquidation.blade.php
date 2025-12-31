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

        .no-break {
            page-break-inside: avoid;
            break-inside: avoid;
        }
    </style>
</head>

<body class="bg-white text-black text-xs">
    <div class="max-w-[210mm] mx-auto p-6 bg-white rounded min-h-screen">
        <!-- ENCABEZADO -->
        <div class="mb-6">
            <div class="rounded">
                <h2 class="text-lg font-bold">LIQUIDACIÓN DE PROVEEDOR</h2>
                <p class="font-medium">Proveedor: <span class="">{{ $supplier['name'] }}</span></p>
                <p class="font-medium">Período: 
                    <span class="">{{ date('d/m/Y', strtotime($date_range['start'])) }}</span> - 
                    <span class="">{{ date('d/m/Y', strtotime($date_range['end'])) }}</span>
                </p>
                <p class="font-medium">Fecha de generación: <span class="">{{ date('d/m/Y H:i') }}</span></p>
            </div>
        </div>

        <!-- TABLA DE RECEPCIONES -->
        <h3 class="font-bold mb-2">RECEPCIONES</h3>
        <div class="border rounded-lg overflow-hidden mb-6">
            <table class="w-full text-xs">
                <thead class="border-b bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Producto</th>
                        <th class="p-2 text-center">Peso Neto</th>
                        <th class="p-2 text-center">Precio</th>
                        <th class="p-2 text-center">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $rowIndex = 0;
                    @endphp

                    @foreach ($receptions as $reception)
                        <!-- Encabezado de la recepción -->
                        @php
                            $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                            $rowIndex++;
                        @endphp
                        <tr class="{{ $rowClass }} font-semibold bg-blue-100">
                            <td class="p-2 py-1" colspan="4">
                                Recepción #{{ $reception['id'] }} - {{ date('d/m/Y', strtotime($reception['date'])) }}
                            </td>
                        </tr>

                        <!-- Productos de la recepción -->
                        @foreach ($reception['products'] as $product)
                            @php
                                $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                $rowIndex++;
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="p-2 py-1 pl-6">{{ $product['product']['name'] ?? 'N/A' }}</td>
                                <td class="p-2 py-1 text-center">{{ number_format($product['net_weight'], 2, ',', '.') }} kg</td>
                                <td class="p-2 py-1 text-center">{{ number_format($product['price'], 2, ',', '.') }} €/kg</td>
                                <td class="p-2 py-1 text-center">{{ number_format($product['amount'], 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach

                        <!-- Total Calculado de la recepción -->
                        @php
                            $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                            $rowIndex++;
                        @endphp
                        <tr class="{{ $rowClass }} font-semibold">
                            <td class="p-2 py-1">Total</td>
                            <td class="p-2 py-1 text-center">{{ number_format($reception['calculated_total_net_weight'], 2, ',', '.') }} kg</td>
                            <td class="p-2 py-1 text-center">-</td>
                            <td class="p-2 py-1 text-center">{{ number_format($reception['calculated_total_amount'], 2, ',', '.') }} €</td>
                        </tr>

                        <!-- Total Declarado de la recepción (si existe) -->
                        @if($reception['declared_total_net_weight'] > 0 || $reception['declared_total_amount'] > 0)
                            @php
                                $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                $rowIndex++;
                            @endphp
                            <tr class="{{ $rowClass }} font-semibold bg-yellow-50">
                                <td class="p-2 py-1">Total Declarado</td>
                                <td class="p-2 py-1 text-center">{{ number_format($reception['declared_total_net_weight'], 2, ',', '.') }} kg</td>
                                <td class="p-2 py-1 text-center">-</td>
                                <td class="p-2 py-1 text-center">{{ number_format($reception['declared_total_amount'], 2, ',', '.') }} €</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- TABLA DE SALIDAS DE CEBO -->
        @php
            // Recopilar todas las salidas de cebo: relacionadas y no relacionadas
            $allDispatches = [];
            
            // Asegurar que $receptions y $dispatches sean arrays
            $receptions = $receptions ?? [];
            $dispatches = $dispatches ?? [];
            
            // Agregar salidas relacionadas de las recepciones
            foreach ($receptions as $reception) {
                if (!empty($reception['related_dispatches'])) {
                    foreach ($reception['related_dispatches'] as $dispatch) {
                        $allDispatches[] = $dispatch;
                    }
                }
            }
            
            // Agregar salidas sin recepción relacionada
            foreach ($dispatches as $dispatch) {
                $allDispatches[] = $dispatch;
            }
            
            // Ordenar por fecha
            usort($allDispatches, function($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });
        @endphp
        
        @if(count($allDispatches) > 0)
        <h3 class="font-bold mb-2">SALIDAS DE CEBO</h3>
        <div class="border rounded-lg overflow-hidden mb-6">
            <table class="w-full text-xs">
                <thead class="border-b bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">Producto</th>
                        <th class="p-2 text-center">Peso Neto</th>
                        <th class="p-2 text-center">Precio</th>
                        <th class="p-2 text-center">Base</th>
                        <th class="p-2 text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $rowIndex = 0;
                    @endphp

                    @foreach ($allDispatches as $dispatch)
                        <!-- Encabezado de la salida -->
                        @php
                            $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                            $rowIndex++;
                        @endphp
                        <tr class="{{ $rowClass }} font-semibold bg-orange-100">
                            <td class="p-2 py-1" colspan="5">
                                Salida #{{ $dispatch['id'] }} - {{ date('d/m/Y', strtotime($dispatch['date'])) }}
                            </td>
                        </tr>

                        <!-- Productos de la salida -->
                        @foreach ($dispatch['products'] as $product)
                            @php
                                $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                $rowIndex++;
                                $productBaseAmount = $product['amount'] ?? 0;
                                $productIvaRate = $dispatch['iva_rate'] ?? 0;
                                $productIvaAmount = round($productBaseAmount * ($productIvaRate / 100), 2);
                                $productTotalAmount = round($productBaseAmount + $productIvaAmount, 2);
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="p-2 py-1 pl-6">{{ $product['product']['name'] ?? 'N/A' }}</td>
                                <td class="p-2 py-1 text-center">{{ number_format($product['net_weight'], 2, ',', '.') }} kg</td>
                                <td class="p-2 py-1 text-center">{{ number_format($product['price'], 2, ',', '.') }} €/kg</td>
                                <td class="p-2 py-1 text-center">{{ number_format($productBaseAmount, 2, ',', '.') }} €</td>
                                <td class="p-2 py-1 text-center">{{ number_format($productTotalAmount, 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach

                        <!-- Total de la salida -->
                        @php
                            $rowClass = $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                            $rowIndex++;
                        @endphp
                        <tr class="{{ $rowClass }} font-semibold">
                            <td class="p-2 py-1">Total</td>
                            <td class="p-2 py-1 text-center">{{ number_format($dispatch['total_net_weight'], 2, ',', '.') }} kg</td>
                            <td class="p-2 py-1 text-center">-</td>
                            <td class="p-2 py-1 text-center">{{ number_format($dispatch['base_amount'] ?? $dispatch['total_amount'], 2, ',', '.') }} €</td>
                            <td class="p-2 py-1 text-center">{{ number_format($dispatch['total_amount'], 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- RESUMEN FINAL -->
        @php
            // Calcular totales calculados (reales) de recepciones
            $totalCalculatedWeight = 0;
            $totalCalculatedAmount = 0;
            foreach ($receptions as $reception) {
                $totalCalculatedWeight += $reception['calculated_total_net_weight'];
                $totalCalculatedAmount += $reception['calculated_total_amount'];
            }
            
            // Calcular diferencias (calculado - declarado)
            $weightDifference = $totalCalculatedWeight - $summary['total_declared_weight'];
            $amountDifference = $totalCalculatedAmount - $summary['total_declared_amount'];
        @endphp
        <div class="mt-6 border rounded-lg overflow-hidden bg-gray-50 no-break">
            <div class="font-bold p-2 bg-gray-800 w-full border-b text-white">RESUMEN GLOBAL</div>
            <div class="p-4">
                <!-- 1. TOTAL RECEPCIONES -->
                <div class="mb-4 pb-4 border-b">
                    <h4 class="font-bold mb-2 text-lg">TOTAL RECEPCIONES</h4>
                    <p><strong>Cantidad:</strong> {{ $summary['total_receptions'] }}</p>
                    <p><strong>Peso Total:</strong> {{ number_format($summary['total_receptions_weight'], 2, ',', '.') }} kg</p>
                    <p><strong>Importe Total:</strong> {{ number_format($summary['total_receptions_amount'], 2, ',', '.') }} €</p>
                </div>
                
                <!-- 2. TOTAL DECLARADO -->
                <div class="mb-4 pb-4 border-b">
                    <h4 class="font-bold mb-2 text-lg">TOTAL DECLARADO</h4>
                    <p><strong>Peso Total Declarado:</strong> {{ number_format($summary['total_declared_weight'], 2, ',', '.') }} kg</p>
                    <p><strong>Importe Total Declarado:</strong> {{ number_format($summary['total_declared_amount'], 2, ',', '.') }} €</p>
                </div>
                
                <!-- 3. TOTAL DIFERENCIA -->
                <div class="mb-4 pb-4 border-b">
                    <h4 class="font-bold mb-2 text-lg">TOTAL DIFERENCIA</h4>
                    <p><strong>Diferencia de Peso:</strong> 
                        <span class="{{ $weightDifference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($weightDifference, 2, ',', '.') }} kg
                        </span>
                    </p>
                    <p><strong>Diferencia de Importe:</strong> 
                        <span class="{{ $amountDifference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($amountDifference, 2, ',', '.') }} €
                        </span>
                    </p>
                    <p><strong>Porcentaje No Declarado:</strong> 
                        <span class="{{ $summary['percentage_not_declared'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($summary['percentage_not_declared'] ?? 0, 2, ',', '.') }} %
                        </span>
                    </p>
                </div>
                
                <!-- 4. TOTAL CEBO -->
                <div class="mb-4 pb-4 border-b">
                    <h4 class="font-bold mb-2 text-lg">TOTAL CEBO</h4>
                    <p><strong>Cantidad:</strong> {{ $summary['total_dispatches'] }}</p>
                    <p><strong>Peso Total:</strong> {{ number_format($summary['total_dispatches_weight'], 2, ',', '.') }} kg</p>
                    <p><strong>Importe Base:</strong> {{ number_format($summary['total_dispatches_base_amount'] ?? 0, 2, ',', '.') }} €</p>
                    @if(($summary['total_dispatches_iva_amount'] ?? 0) > 0)
                    <p><strong>IVA:</strong> {{ number_format($summary['total_dispatches_iva_amount'] ?? 0, 2, ',', '.') }} €</p>
                    @endif
                    <p><strong>Importe Total (con IVA):</strong> {{ number_format($summary['total_dispatches_amount'], 2, ',', '.') }} €</p>
                </div>
                
                <!-- TOTALES DE PAGO (siempre se muestran) -->
                @php
                    $paymentTotals = $payment_totals ?? null;
                    $showTransferPayment = $show_transfer_payment ?? true;
                @endphp
                @if($paymentTotals && ($paymentTotals['total_cash'] !== null || $paymentTotals['total_transfer'] !== null))
                <div class="mt-4 pt-4 border-t">
                    <h4 class="font-bold mb-2 text-lg">TOTALES DE PAGO</h4>
                    
                    <!-- Total Efectivo -->
                    <div class="mb-4 pb-4 border-b">
                        <p class="text-lg font-bold">
                            <strong>Total Efectivo:</strong> 
                            <span class="{{ ($paymentTotals['total_cash'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($paymentTotals['total_cash'] ?? 0, 2, ',', '.') }} €
                            </span>
                        </p>
                        <p class="text-xs text-gray-600 mt-1">
                            @if(($paymentTotals['payment_method'] ?? null) === 'cash' && ($paymentTotals['has_iva_in_dispatches'] ?? false))
                                (Total Recepción - Total Declarado - Total Salida Cebo con IVA)
                            @elseif(($summary['total_dispatches_base_amount'] ?? 0) > 0 && !($paymentTotals['has_iva_in_dispatches'] ?? false))
                                (Total Recepción - Total Declarado - Total Salida Cebo sin IVA)
                            @else
                                (Total Recepción - Total Declarado)
                            @endif
                        </p>
                    </div>
                    
                    <!-- Total Transferencia (solo si show_transfer_payment está activado) -->
                    @if($showTransferPayment)
                    <div class="mb-2">
                        <p class="text-lg font-bold">
                            <strong>Total Transferencia:</strong> 
                            <span class="{{ ($paymentTotals['total_transfer_final'] ?? $paymentTotals['total_transfer'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($paymentTotals['total_transfer_final'] ?? $paymentTotals['total_transfer'] ?? 0, 2, ',', '.') }} €
                            </span>
                        </p>
                        <p class="text-xs text-gray-600 mt-1">
                            @if(($paymentTotals['payment_method'] ?? null) === 'transfer' && ($paymentTotals['has_iva_in_dispatches'] ?? false))
                                (Total Declarado con IVA - Total Salida Cebo con IVA)
                            @elseif(($paymentTotals['has_management_fee'] ?? false) && ($paymentTotals['management_fee'] ?? 0) > 0)
                                (Total Declarado con IVA - Gasto de Gestión)
                            @else
                                (Total Declarado con IVA)
                            @endif
                        </p>
                        
                        <!-- Desglose en lista -->
                        <div class="mt-3 ml-4">
                            <ul class="list-disc list-inside space-y-1 text-sm">
                                <li><strong>Total Declarado (con IVA):</strong> {{ number_format($summary['total_declared_with_iva'] ?? 0, 2, ',', '.') }} €</li>
                                <li class="ml-4 text-xs text-gray-600">
                                    - Total Declarado (sin IVA): {{ number_format($summary['total_declared_amount'] ?? 0, 2, ',', '.') }} €
                                </li>
                                <li class="ml-4 text-xs text-gray-600">
                                    - IVA (10%): {{ number_format(($summary['total_declared_with_iva'] ?? 0) - ($summary['total_declared_amount'] ?? 0), 2, ',', '.') }} €
                                </li>
                                @if(($paymentTotals['payment_method'] ?? null) === 'transfer' && ($paymentTotals['has_iva_in_dispatches'] ?? false))
                                <li><strong>(-) Total Salida Cebo (con IVA):</strong> {{ number_format($summary['total_dispatches_amount'] ?? 0, 2, ',', '.') }} €</li>
                                @endif
                                @if(($paymentTotals['has_management_fee'] ?? false) && ($paymentTotals['management_fee'] ?? 0) > 0)
                                <li><strong>(-) Gasto de Gestión (2.5%):</strong> {{ number_format($paymentTotals['management_fee'], 2, ',', '.') }} €</li>
                                <li class="ml-4 text-xs text-gray-600">(Sobre Total Declarado sin IVA: {{ number_format($summary['total_declared_amount'] ?? 0, 2, ',', '.') }} €)</li>
                                @endif
                            </ul>
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>

    </div>
</body>

</html>

