<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a PesquerApp</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f4f7; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1e40af; padding: 32px 24px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 24px; }
        .body { padding: 32px 24px; color: #333; line-height: 1.6; }
        .body h2 { color: #1e40af; margin-top: 0; }
        .btn { display: inline-block; padding: 14px 28px; background: #1e40af; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 16px 0; }
        .info { background: #f0f4ff; border-radius: 6px; padding: 16px; margin: 16px 0; }
        .info p { margin: 4px 0; }
        .footer { padding: 20px 24px; text-align: center; color: #888; font-size: 13px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PesquerApp</h1>
        </div>
        <div class="body">
            <h2>Bienvenido, {{ $companyName }}</h2>
            <p>Tu cuenta en PesquerApp ya está activa y lista para usar.</p>

            <div class="info">
                <p><strong>URL de acceso:</strong> <a href="{{ $tenantUrl }}">{{ $tenantUrl }}</a></p>
                <p><strong>Tu email:</strong> {{ $adminEmail }}</p>
            </div>

            <p>Para acceder, haz clic en el siguiente enlace e introduce tu email. Recibirás un código de verificación (no necesitas contraseña).</p>

            <p style="text-align: center;">
                <a href="{{ $tenantUrl }}" class="btn">Acceder a PesquerApp</a>
            </p>

            <p>Si tienes alguna duda, contacta con nuestro equipo de soporte.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} PesquerApp. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
