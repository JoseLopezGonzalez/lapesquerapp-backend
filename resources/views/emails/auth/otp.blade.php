<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml"
  xmlns:v="urn:schemas-microsoft-com:vml"
  xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="x-apple-disable-message-reformatting">
  <title>Tu código de acceso</title>
  <!--[if mso]>
  <noscript><xml><o:OfficeDocumentSettings>
    <o:PixelsPerInch>96</o:PixelsPerInch>
  </o:OfficeDocumentSettings></xml></noscript>
  <![endif]-->
  <style type="text/css">
    #outlook a { padding: 0; }
    body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
    a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; }
    .a6S { display: none !important; opacity: 0.01 !important; }

    @media only screen and (max-width: 620px) {
      .email-container { width: 100% !important; }
      .pad { padding: 28px 20px !important; }
      .pad-sm { padding: 16px 20px !important; }
      .code-text { font-size: 30px !important; letter-spacing: 0.2em !important; }
    }
  </style>
</head>
<body width="100%" style="margin:0;padding:0;background-color:#fafafa;">

  {{-- Preview text oculto en la bandeja de entrada --}}
  <div aria-hidden="true" style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;font-family:sans-serif;">
    Tu código de acceso: {{ $code }} — válido {{ $expiresMinutes }} minutos ‌ ‌ ‌ ‌ ‌ ‌ ‌ ‌
  </div>

  @php
    $companyName = tenantSetting('company.name', 'PesquerApp');
  @endphp

  {{-- Outer wrapper --}}
  <table role="presentation" cellpadding="0" cellspacing="0" border="0"
         width="100%" bgcolor="#fafafa" style="background-color:#fafafa;">
    <tr>
      <td align="center" valign="top" style="padding:40px 16px 48px;">

        {{-- Contenedor principal 600px --}}
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"
               width="600" class="email-container" style="max-width:600px;">

          {{-- CARD PRINCIPAL --}}
          <tr>
            <td bgcolor="#ffffff"
                style="background-color:#ffffff;border:1px solid #e4e4e7;border-radius:12px;">

              <table role="presentation" cellpadding="0" cellspacing="0"
                     border="0" width="100%">

                {{-- Franja de acento superior --}}
                <tr>
                  <td bgcolor="#09090b" height="4"
                      style="background-color:#09090b;height:4px;line-height:4px;font-size:4px;border-radius:11px 11px 0 0;">&nbsp;</td>
                </tr>

                {{-- CABECERA + CÓDIGO --}}
                <tr>
                  <td class="pad" style="padding:32px 40px 24px;">
                    <h1 style="margin:0 0 20px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:20px;font-weight:700;color:#09090b;line-height:1.3;">
                      Código de acceso — {{ $companyName }}
                    </h1>
                    <table role="presentation" cellpadding="0" cellspacing="0"
                           border="0" width="100%">
                      <tr>
                        <td bgcolor="#f4f4f5" align="center"
                            style="background-color:#f4f4f5;border:1px solid #e4e4e7;border-radius:8px;padding:20px 24px;">
                          <span class="code-text"
                                style="display:block;text-align:center;font-family:ui-monospace,'SF Mono','Cascadia Code','Roboto Mono',Consolas,'Courier New',monospace;font-size:40px;font-weight:700;letter-spacing:0.35em;color:#09090b;line-height:1;">
                            {{ $code }}
                          </span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>

                {{-- AVISO --}}
                <tr>
                  <td style="padding:0 40px 24px;">
                    <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#a1a1aa;line-height:1.5;">
                      Válido {{ $expiresMinutes }} min. No lo compartas. Si no lo has solicitado, ignora este correo.
                    </p>
                  </td>
                </tr>

              </table>
            </td>
          </tr>

          {{-- PIE DE PÁGINA --}}
          <tr>
            <td align="center" style="padding:20px 0 4px;">
              <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#a1a1aa;">
                &copy; {{ date('Y') }} {{ $companyName }}
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>
