<x-mail::layout>

    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="tenantSetting('company.website_url')">
            @php($logoUrl = tenantSetting('company.logo_url_small'))
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ config('company.name') }}" width="200" style="width:200px; height:auto; display:block; margin:0 auto;">
            @endif
            <div style="margin-top:8px; font-weight:400; font-size:16px; color:white;">
                {{ tenantSetting('company.name') }}
            </div>
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
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:16px;">
            <tr>
                <td style="text-align:center;"></td>
                    <span style="font-size:13px;color:#334155;">Gestiona tu negocio pesquero con <strong>La Pesquerapp ERP</strong>. </span>
                    <a href="https://lapesquerapp.com" target="_blank" rel="noopener noreferrer" style="font-size:13px;color:#0ea5e9;text-decoration:none;">Conócela</a>
                </td>
            </tr>
        </table>
        <x-mail::footer>
            © {{ date('Y') }} La Pesquerapp ERP. @lang('All rights reserved.') — <a href="https://lapesquerapp.com" target="_blank" rel="noopener noreferrer">lapesquerapp.com</a>
        </x-mail::footer>
    </x-slot:footer>

</x-mail::layout>