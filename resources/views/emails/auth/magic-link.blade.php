<x-mail::message>
# Inicia sesión

Hemos recibido una solicitud para iniciar sesión. Haz clic en el botón para acceder:

<x-mail::button :url="$magicLinkUrl" color="primary">
Iniciar sesión
</x-mail::button>

Este enlace es válido durante **{{ $expiresMinutes }} minutos**. No lo compartas con nadie.

Si no has solicitado este enlace, puedes ignorar este correo.
</x-mail::message>
