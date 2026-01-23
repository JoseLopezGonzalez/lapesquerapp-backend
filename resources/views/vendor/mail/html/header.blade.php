@props(['url'])

<tr style="display: none;">
    <td>
        {{ tenantSetting('company.name') }}
    </td>
</tr>

<tr>
    <td class="header">
        <a href="{{ $url ?? tenantSetting('company.website_url') }}" style="display: inline-block; color: white;">
            @php($logoUrl = tenantSetting('company.logo_url_small'))
            @if(!empty($logoUrl) && !empty(trim($slot)))
                <img src="{{ $logoUrl }}" class="logo" alt="Logo {{ tenantSetting('company.name') }}" style="max-width:120px; max-height:80px; width:auto; height:auto;">
            @else
                {{ $slot }}
            @endif
        </a>
    </td>
</tr>