<x-mail::message>
# Accede a tu cuenta

Has solicitado iniciar sesión. Elige la opción que te vaya mejor:

## Enlace rápido

Si estás en el mismo dispositivo donde recibes el correo, haz clic en el botón:

<x-mail::button :url="$magicLinkUrl" color="primary">
Acceder
</x-mail::button>

---

## Código de acceso

Si abres el correo en otro dispositivo, copia este código y pégalo en la web:

<x-mail::panel>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border: 1px solid #e4e4e7; border-radius: 12px;">
<tr>
<td style="color: #18181b; font-family: ui-monospace, 'SF Mono', Monaco, monospace; font-size: 28px; font-weight: 600; letter-spacing: 0.2em; padding: 16px 20px 16px 24px;">{{ $code }}</td>
<td style="color: #71717a; font-size: 13px; padding: 16px 24px 16px 8px; text-align: right; vertical-align: middle;">📋 Copia</td>
</tr>
</table>
</x-mail::panel>

---

El enlace y el código son válidos durante **{{ $expiresMinutes }} minutos**. No los compartas con nadie.

Si no has solicitado este acceso, puedes ignorar este correo.
</x-mail::message>
