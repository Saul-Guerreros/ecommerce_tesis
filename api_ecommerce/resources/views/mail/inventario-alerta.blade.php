<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Alertas de reposición de stock</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#333;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
          <tr>
            <td style="background:#e55353;color:#fff;padding:18px 24px;font-size:18px;font-weight:bold;">
              Z Informática · Alertas de reposición de stock
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              <p style="margin:0 0 16px;line-height:1.5;">
                El sistema de gestión inteligente de inventario detectó productos que necesitan reposición urgente
                según la demanda prevista.
              </p>

              <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
                <tr style="background:#f0f0f0;text-align:left;">
                  <th>Estado</th><th>Producto</th><th>Depósito</th>
                  <th style="text-align:right;">Disponible</th><th style="text-align:right;">Cobertura</th>
                  <th style="text-align:right;">Sugerido</th>
                </tr>
                @foreach ($alertas as $a)
                  <tr style="border-bottom:1px solid #eee;">
                    <td>
                      <span style="display:inline-block;padding:2px 8px;border-radius:10px;color:#fff;font-size:11px;
                                   background:{{ $a->tipo === 'agotado' ? '#212631' : '#e55353' }};">
                        {{ $a->tipo === 'agotado' ? 'Agotado' : 'Crítico' }}
                      </span>
                    </td>
                    <td>{{ $a->producto }}<br><span style="color:#999;font-size:11px;">{{ $a->codigo }}</span></td>
                    <td>{{ $a->deposito }}</td>
                    <td style="text-align:right;">{{ number_format((float) $a->disponible, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ $a->cobertura_dias !== null ? number_format((float) $a->cobertura_dias, 0, ',', '.') . ' d' : '-' }}</td>
                    <td style="text-align:right;font-weight:bold;">{{ number_format((float) $a->sugerido, 0, ',', '.') }}</td>
                  </tr>
                @endforeach
              </table>

              <p style="margin:16px 0 20px;color:#666;font-size:13px;">
                Pendientes en total: {{ $totales['agotado'] ?? 0 }} agotado(s), {{ $totales['critico'] ?? 0 }} crítico(s),
                {{ $totales['reponer'] ?? 0 }} para reponer y {{ $totales['sobrestock'] ?? 0 }} con sobrestock.
              </p>

              <a href="{{ $url }}" style="display:inline-block;background:#1e87f0;color:#fff;text-decoration:none;padding:12px 22px;border-radius:4px;">
                Ver en el panel
              </a>
            </td>
          </tr>
          <tr>
            <td style="padding:14px 24px;background:#fafafa;color:#999;font-size:12px;">
              Aviso automático. Cada alerta se notifica una sola vez; volverá a avisarse solo si empeora.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>