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
<table class="otp-copy-block" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="otp-code">{{ $code }}</td>
<td class="otp-copy-hint">📋 Copia</td>
</tr>
</table>
</x-mail::panel>

---

El enlace y el código son válidos durante **{{ $expiresMinutes }} minutos**. No los compartas con nadie.

Si no has solicitado este acceso, puedes ignorar este correo.
</x-mail::message>
