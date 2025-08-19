<x-mail::layout>

    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('company.website_url')">
            @php($logoUrl = config('company.logo_url_small'))
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ config('company.name') }}" style="height:45px; max-height:45px; width:auto; display:block; margin:0 auto;">
            @endif
            <div style="margin-top:8px; font-weight:600; font-size:16px; color:#0f172a;">
                {{ config('company.name') }}
            </div>
            @if(!empty($logoUrl))
                <div style="margin-top:4px; font-size:12px; color:#64748b;">
                    {{ $logoUrl }}
                </div>
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
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:16px;">
            <tr>
                <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px 16px;text-align:center;">
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