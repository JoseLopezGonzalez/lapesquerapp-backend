<x-mail::layout>

    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="tenantSetting('company.website_url')">
            @php($logoUrl = tenantSetting('company.logo_url_small'))
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ tenantSetting('company.name') }}" width="200" style="width:200px; height:auto; display:block; margin:0 auto;">
            @endif
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            
        </x-mail::footer>
    </x-slot:footer>

</x-mail::layout>