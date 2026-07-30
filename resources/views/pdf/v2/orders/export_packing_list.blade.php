<!DOCTYPE html>
<html>

<head>
    <title>Export Packing List</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'DejaVu Sans';
        }

        @page {
            size: A4 portrait;
        }
    </style>
</head>

<body class="h-full">

    @php
        $shippingDetail = $entity->maritimeShippingDetail;
        $customsBroker = $shippingDetail?->customsBroker;
        $customer = $entity->customer;

        $ultimateConsigneeName = $shippingDetail?->ultimate_consignee_name ?: $customer?->name;
        $ultimateConsigneeAddress = $shippingDetail?->ultimate_consignee_address ?: $entity->shipping_address;

        $originCountry = $shippingDetail?->origin_country ?: tenantSetting('company.address.country');
        $destinationCountry = $shippingDetail?->destination_country ?: $customer?->country?->name;

        $packingListSummary = $container->packingListSummary;
        $packingListTotals = $container->packingListTotals;
    @endphp

    <div class="flex flex-col max-w-[210mm] mx-auto p-6 bg-white rounded text-black text-xs min-h-screen">

        {{-- Cabecera estándar --}}
        <div class="flex justify-between items-end mb-6">
            <div class="flex items-center gap-2">
                <div>
                    <h1 class="text-md font-bold">{{ tenantSetting('company.name') }}</h1>
                    <p>{{ tenantSetting('company.address.street') }} - {{ tenantSetting('company.address.postal_code') }}
                        {{ tenantSetting('company.address.city') }}</p>
                    <p>Tel: {{ tenantSetting('company.contact.phone_admin') }}</p>
                    <p>{{ tenantSetting('company.contact.email_admin') }}</p>
                    <p>{{ tenantSetting('company.sanitary_number') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-4">
                <div class="rounded text-end">
                    <h2 class="text-lg font-bold">Export Packing List</h2>
                    <p class="font-medium">{{ $entity->formattedId }}</p>
                    <p class="font-medium">Fecha de carga:
                        {{ $entity->load_date ? date('d/m/Y', strtotime($entity->load_date)) : '-' }}</p>
                    <p class="font-medium">Buyer Reference: {{ $entity->buyer_reference }}</p>
                </div>
                <div class="flex flex-col items-center">
                    <div class="p-1 border rounded flex items-center justify-center bg-white">
                        <img alt='Barcode Generator TEC-IT'
                            src="{{ 'https://barcode.tec-it.com/barcode.ashx?data=Pedido%3A' . $entity->id . '&code=QRCode&eclevel=L' }}"
                            class="w-[4.1rem] h-[4.1rem]" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Shipper / Intermediate Consignee / Ultimate Consignee --}}
        <div class="grid grid-cols-3 gap-3 mb-4 items-stretch">
            <div class="border border-gray-300 rounded-lg overflow-hidden flex flex-col">
                <div class="bg-gray-800 text-white p-2 font-bold">Shipper / Exporter</div>
                <div class="p-2 bg-gray-50 flex-1">
                    <p class="font-semibold">{{ tenantSetting('company.name') }}</p>
                    <p>{{ tenantSetting('company.address.street') }}</p>
                    <p>{{ tenantSetting('company.address.postal_code') }} {{ tenantSetting('company.address.city') }}
                        ({{ tenantSetting('company.address.province') }})</p>
                    <p>{{ tenantSetting('company.address.country') }}</p>
                </div>
            </div>
            <div class="border border-gray-300 rounded-lg overflow-hidden flex flex-col">
                <div class="bg-gray-800 text-white p-2 font-bold">Intermediate Consignee</div>
                <div class="p-2 bg-gray-50 flex-1">
                    @if ($customsBroker)
                        <p class="font-semibold">{{ $customsBroker->name }}</p>
                        <p>{!! nl2br(e($customsBroker->address)) !!}</p>
                        @if ($customsBroker->phone)
                            <p>{{ $customsBroker->phone }}</p>
                        @endif
                        @if ($customsBroker->email)
                            <p>Email: {{ $customsBroker->email }}</p>
                        @endif
                    @else
                        <p class="italic text-gray-500">Sin agente de aduanas asignado</p>
                    @endif
                </div>
            </div>
            <div class="border border-gray-300 rounded-lg overflow-hidden flex flex-col">
                <div class="bg-gray-800 text-white p-2 font-bold">Ultimate Consignee</div>
                <div class="p-2 bg-gray-50 flex-1">
                    <p class="font-semibold">{{ $ultimateConsigneeName }}</p>
                    <p>{!! nl2br(e($ultimateConsigneeAddress)) !!}</p>
                    @if ($customer?->country)
                        <p>{{ $customer->country->name }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Metadatos de envío --}}
        <div class="grid grid-cols-3 gap-3 mb-4 text-xs items-stretch">
            <div class="border border-gray-300 rounded-lg overflow-hidden flex flex-col">
                <div class="bg-gray-800 text-white p-2 font-bold">Shipment References</div>
                <div class="p-2 bg-white flex-1 space-y-1.5">
                    <p><span class="text-[10px] uppercase text-gray-500 block">Commercial Invoice No.</span>
                        <span class="font-semibold">{{ $shippingDetail?->export_invoice_number ?? '-' }}</span></p>
                    <p><span class="text-[10px] uppercase text-gray-500 block">Booking No.</span>
                        <span class="font-semibold">{{ $shippingDetail?->booking_number ?? '-' }}</span></p>
                    <p><span class="text-[10px] uppercase text-gray-500 block">Vessel Name</span>
                        <span class="font-semibold">{{ $shippingDetail?->vessel_name ?? '-' }}</span></p>
                    <p><span class="text-[10px] uppercase text-gray-500 block">Voyage Number</span>
                        <span class="font-semibold">{{ $shippingDetail?->voyage_number ?? '-' }}</span></p>
                </div>
            </div>
            <div class="border border-gray-300 rounded-lg overflow-hidden flex flex-col">
                <div class="bg-gray-800 text-white p-2 font-bold">Container</div>
                <div class="p-2 bg-white flex-1 space-y-1.5">
                    <p><span class="text-[10px] uppercase text-gray-500 block">Container No.</span>
                        <span class="font-semibold">{{ $container->container_number }}</span></p>
                    <p><span class="text-[10px] uppercase text-gray-500 block">Seal No.</span>
                        <span class="font-semibold">{{ $container->seal_number ?? '-' }}</span></p>
                    <p><span class="text-[10px] uppercase text-gray-500 block">Sea Waybill No. (SWB)</span>
                        <span class="font-semibold">{{ $shippingDetail?->swb_number ?? '-' }}</span></p>
                </div>
            </div>
            <div class="border border-gray-300 rounded-lg overflow-hidden flex flex-col">
                <div class="bg-gray-800 text-white p-2 font-bold">Trade</div>
                <div class="p-2 bg-white flex-1 space-y-1.5">
                    <p><span class="text-[10px] uppercase text-gray-500 block">Country of Origin</span>
                        <span class="font-semibold">{{ $originCountry ?? '-' }}</span></p>
                    <p><span class="text-[10px] uppercase text-gray-500 block">Country of Final Destination</span>
                        <span class="font-semibold">{{ $destinationCountry ?? '-' }}</span></p>
                    <p><span class="text-[10px] uppercase text-gray-500 block">Incoterm</span>
                        <span class="font-semibold">{{ $entity->incoterm?->code ?? '-' }}</span></p>
                </div>
            </div>
        </div>

        {{-- Mercancía --}}
        <div class="w-full mb-2 rounded-lg overflow-hidden border">
            <table class="w-full">
                <thead class="border-b">
                    <tr class="bg-gray-100">
                        <th class="p-2 text-left">Description of Goods</th>
                        <th class="p-2 text-center">Boxes</th>
                        <th class="p-2 text-center">Net Weight (kg)</th>
                        <th class="p-2 text-center">Net Weight (lb)</th>
                        <th class="p-2 text-center">Gross Weight (kg)</th>
                        <th class="p-2 text-center">Gross Weight (lb)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($packingListSummary as $speciesGroup)
                        @php
                            $hsCodes = collect($speciesGroup['products'])
                                ->pluck('product.hs_code')
                                ->filter()
                                ->unique()
                                ->values();
                        @endphp
                        <tr class="bg-gray-200">
                            <td colspan="6" class="p-2">
                                <span class="font-bold">{{ $speciesGroup['species']->name }}</span>
                                <span class="italic">
                                    ({{ $speciesGroup['species']->scientific_name }} - {{ $speciesGroup['species']->fao }})
                                </span>
                                @if ($hsCodes->isNotEmpty())
                                    <span class="block text-[10px] text-gray-600">HTSUS: {{ $hsCodes->implode(', ') }}</span>
                                @endif
                            </td>
                        </tr>
                        @foreach ($speciesGroup['products'] as $productGroup)
                            <tr class="{{ $loop->even ? 'bg-white' : 'bg-gray-50' }}">
                                <td class="p-2">{{ $productGroup['product']->name }}</td>
                                <td class="p-2 text-center">{{ $productGroup['boxes'] }}</td>
                                <td class="p-2 text-center">{{ number_format($productGroup['netWeightKg'], 2, ',', '.') }}</td>
                                <td class="p-2 text-center">{{ number_format($productGroup['netWeightLb'], 2, ',', '.') }}</td>
                                <td class="p-2 text-center">{{ number_format($productGroup['grossWeightKg'], 2, ',', '.') }}</td>
                                <td class="p-2 text-center">{{ number_format($productGroup['grossWeightLb'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
                <tfoot class="border-t">
                    <tr class="bg-gray-100">
                        <td class="p-2 font-bold">TOTAL</td>
                        <td class="p-2 text-center font-bold">{{ $packingListTotals['boxes'] }}</td>
                        <td class="p-2 text-center font-bold">{{ number_format($packingListTotals['netWeightKg'], 2, ',', '.') }}</td>
                        <td class="p-2 text-center font-bold">{{ number_format($packingListTotals['netWeightLb'], 2, ',', '.') }}</td>
                        <td class="p-2 text-center font-bold">{{ number_format($packingListTotals['grossWeightKg'], 2, ',', '.') }}</td>
                        <td class="p-2 text-center font-bold">{{ number_format($packingListTotals['grossWeightLb'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="text-[10px] text-gray-500 mt-2">Country of Origin of Goods: {{ $originCountry ?? '-' }} · Country of Final Destination: {{ $destinationCountry ?? '-' }}</p>

    </div>
</body>

</html>
