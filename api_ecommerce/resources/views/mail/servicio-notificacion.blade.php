<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $asunto }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#333;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
          <tr>
            <td style="background:#1e87f0;color:#fff;padding:18px 24px;font-size:18px;font-weight:bold;">
              Z Informática · Servicio técnico
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              <p style="margin:0 0 12px;">Hola{{ $nombre ? ' ' . $nombre : '' }},</p>
              <h2 style="margin:0 0 12px;font-size:20px;color:#222;">{{ $titulo }}</h2>
              <p style="margin:0 0 8px;color:#666;font-size:13px;">Solicitud <strong>{{ $numero }}</strong></p>
              <p style="margin:0 0 20px;line-height:1.5;">{{ $mensaje }}</p>
              @if ($invitarRegistro)
                <p style="margin:0 0 16px;line-height:1.5;color:#555;font-size:14px;">
                  Creá tu cuenta en nuestra tienda con <strong>este mismo correo</strong> y vas a poder seguir
                  el estado de tu servicio, ver el presupuesto y descargar tus constancias en línea.
                </p>
                <a href="{{ $url }}" style="display:inline-block;background:#1e87f0;color:#fff;text-decoration:none;padding:12px 22px;border-radius:4px;">
                  Crear mi cuenta
                </a>
              @else
                <a href="{{ $url }}" style="display:inline-block;background:#1e87f0;color:#fff;text-decoration:none;padding:12px 22px;border-radius:4px;">
                  Ver mi servicio
                </a>
              @endif
            </td>
          </tr>
          <tr>
            <td style="padding:14px 24px;background:#fafafa;color:#999;font-size:12px;">
              Este es un mensaje automático. No respondas a este correo.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
