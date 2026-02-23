<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de acceso</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f4f7; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1e40af; padding: 32px 24px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 24px; }
        .body { padding: 32px 24px; color: #333; line-height: 1.6; }
        .body h2 { color: #1e40af; margin-top: 0; }
        .btn { display: inline-block; padding: 14px 28px; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 8px 4px; }
        .btn-approve { background: #16a34a; }
        .btn-reject { background: #dc2626; }
        .info { background: #fef3c7; border-radius: 6px; padding: 16px; margin: 16px 0; border-left: 4px solid #f59e0b; }
        .footer { padding: 20px 24px; text-align: center; color: #888; font-size: 13px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PesquerApp</h1>
        </div>
        <div class="body">
            <h2>Solicitud de acceso a tu cuenta</h2>

            <p>El equipo de soporte de PesquerApp ha solicitado acceso temporal a tu cuenta en <strong>{{ $tenantName }}</strong> para proporcionarte asistencia.</p>

            <div class="info">
                <p><strong>Importante:</strong> Si apruebas, el equipo de soporte tendrá acceso durante 30 minutos. Puedes revocar el acceso en cualquier momento.</p>
            </div>

            <p style="text-align: center;">
                <a href="{{ $approveUrl }}" class="btn btn-approve">Aprobar acceso (30 min)</a>
                <a href="{{ $rejectUrl }}" class="btn btn-reject">Rechazar</a>
            </p>

            <p>Si no has solicitado asistencia, puedes ignorar este correo o rechazar la solicitud.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} PesquerApp. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
