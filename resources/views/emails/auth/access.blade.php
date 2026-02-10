<x-mail::message>
# Accede a tu cuenta

Has solicitado iniciar sesión. Puedes hacerlo de dos formas:

**Opción 1 — Haz clic en el enlace** (si estás en el mismo dispositivo donde recibes el correo):

<x-mail::button :url="$magicLinkUrl" color="primary">
Acceder
</x-mail::button>

**Opción 2 — Introduce este código** (si abres el correo en otro dispositivo o prefieres pegar el código en la web):

**{{ $code }}**

El enlace y el código son válidos durante **{{ $expiresMinutes }} minutos**. No los compartas con nadie.

Si no has solicitado este acceso, puedes ignorar este correo.
</x-mail::message>
