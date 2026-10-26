<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Factura electrónica {{ $numero }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#333;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
          <tr>
            <td style="background:#1e87f0;color:#fff;padding:18px 24px;font-size:18px;font-weight:bold;">
              {{ $empresa }} · Factura electrónica
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              <p style="margin:0 0 12px;">Hola{{ $nombre ? ' ' . $nombre : '' }},</p>
              <p style="margin:0 0 16px;line-height:1.5;">
                Te enviamos tu factura electrónica <strong>{{ $numero }}</strong>.
                Adjuntamos el comprobante en PDF y el archivo XML firmado.
              </p>

              @if ($esPrueba)
                <p style="margin:0 0 16px;padding:10px 12px;background:#fff4e5;color:#8a5200;border-radius:4px;font-size:13px;">
                  Documento de prueba: no tiene valor fiscal.
                </p>
              @endif

              <p style="margin:0 0 4px;color:#666;font-size:13px;">Código de control (CDC)</p>
              <p style="margin:0 0 20px;font-family:monospace;font-size:13px;word-break:break-all;">{{ $cdc }}</p>

              <a href="{{ $qr ?: 'https://ekuatia.set.gov.py/consultas' }}"
                 style="display:inline-block;background:#1e87f0;color:#fff;text-decoration:none;padding:12px 22px;border-radius:4px;">
                Verificar en e-Kuatia
              </a>
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