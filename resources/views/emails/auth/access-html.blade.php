<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Accede a tu cuenta</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.5; color: #333333;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td align="center" style="padding: 20px 0;">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff;">
@php($logoUrl = tenantSetting('company.logo_url_small'))
@if(!empty($logoUrl))
<tr>
<td align="center" style="padding: 20px 0 16px 0;">
<img src="{{ $logoUrl }}" alt="{{ tenantSetting('company.name') }}" width="120" style="display:block; max-width:120px; height:auto;" />
</td>
</tr>
@endif
<tr>
<td style="padding: 0 24px 24px 24px;">
<p style="margin: 0 0 16px 0; font-size: 16px; font-weight: bold; color: #333333;">Accede a tu cuenta</p>
<p style="margin: 0 0 20px 0;">Has solicitado iniciar sesión. Puedes hacerlo de dos formas:</p>

<p style="margin: 0 0 8px 0;"><strong>1. Enlace:</strong> Si estás en el mismo dispositivo, haz clic aquí:</p>
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="padding: 0 0 24px 0;">
<a href="{{ $magicLinkUrl }}" target="_blank" style="background-color: #333333; color: #ffffff; text-decoration: none; padding: 10px 20px; font-size: 14px;">Acceder</a>
</td>
</tr>
</table>

<p style="margin: 0 0 8px 0;"><strong>2. Código:</strong> Si abres el correo en otro dispositivo, usa este código en la web:</p>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5;">
<tr>
<td style="padding: 12px 16px; font-size: 22px; font-weight: bold; color: #333333; font-family: 'Courier New', monospace;">{{ $code }}</td>
</tr>
</table>

<p style="margin: 24px 0 0 0; padding-top: 20px; border-top: 1px solid #eeeeee; font-size: 12px; color: #666666;">El enlace y el código son válidos durante {{ $expiresMinutes }} minutos. No los compartas con nadie.</p>
<p style="margin: 8px 0 0 0; font-size: 12px; color: #666666;">Si no has solicitado este acceso, ignora este correo.</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
