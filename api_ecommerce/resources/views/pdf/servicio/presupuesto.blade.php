<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Presupuesto {{ $p->pse_numero }}</title>
@include('pdf.servicio._estilos')
<style>
  .num-col { text-align: right; white-space: nowrap; }
  .totales { width: 45%; margin-left: 55%; border-collapse: collapse; margin-top: 6px; }
  .totales td { padding: 3px 4px; }
  .totales .total td { font-weight: bold; font-size: 12px; border-top: 1px solid #333; }
  .sello { display: inline-block; padding: 2px 8px; border: 1px solid #1e87f0; color: #1e87f0; font-weight: bold; }
</style>
</head>
<body>
  @php
    $gs = fn ($v) => 'Gs. ' . number_format((float) $v, 0, ',', '.');
    $persona = $s->cliente?->persona;
    $codigo  = $p->estado?->epr_codigo;
  @endphp

  <table class="cab"><tr>
    <td>
      <h1>{{ $empresa?->emp_razonsocial ?? 'Z Informática' }}</h1>
      <div class="muted">RUC {{ $empresa?->emp_ruc }} · Tel. {{ $empresa?->emp_telef }}</div>
      <div class="muted">{{ $s->sucursal?->suc_nombre }} — {{ $s->sucursal?->suc_direccion }}</div>
    </td>
    <td class="derecha">
      <div>PRESUPUESTO DE SERVICIO TÉCNICO</div>
      <div class="num">{{ $p->pse_numero }}</div>
      <div class="muted">
        Versión {{ $p->pse_versio }}{{ $p->pse_versio > 1 ? ' (complementario)' : '' }} ·
        {{ $s->sse_numero }} · {{ $s->ordenServicio?->ose_numero }}
      </div>
      <div class="muted">Emitido: {{ $p->pse_enviad?->format('d/m/Y') ?? '—' }}</div>
      @if($codigo !== 'ENVIADO')
        <div style="margin-top:4px"><span class="sello">{{ mb_strtoupper($p->estado?->epr_descri) }} — COPIA</span></div>
      @endif
    </td>
  </tr></table>

  <h2>Cliente y equipo</h2>
  <table class="datos">
    <tr><td class="et">Cliente</td><td>{{ $persona?->nombre_completo }}</td></tr>
    <tr><td class="et">Documento</td><td>{{ $persona?->tipoDocumento?->tpd_codigo ?? 'C.I.' }} {{ $persona?->per_cedula ?: '—' }}</td></tr>
    <tr><td class="et">Equipo</td><td>{{ $s->tipoDispositivo?->tdi_descri }} {{ $s->sse_marca }} {{ $s->sse_modelo }}{{ $s->sse_nroser ? ' · S/N ' . $s->sse_nroser : '' }}</td></tr>
    <tr><td class="et">Problema declarado</td><td>{{ $s->sse_proble }}</td></tr>
  </table>

  <h2>Fallas detectadas en el diagnóstico</h2>
  @if($fallas->isEmpty())
    <p class="muted">Sin fallas catalogadas.</p>
  @else
    <ul style="margin:0; padding-left:16px;">
      @foreach($fallas as $f)<li>{{ $f }}</li>@endforeach
    </ul>
  @endif
  @if($p->pse_versio > 1)
    <p class="muted">Este presupuesto complementario incluye solo lo adicional a lo ya aprobado.</p>
  @endif

  <h2>Detalle</h2>
  <table class="lista">
    <tr>
      <th>Tipo</th><th>Descripción</th>
      <th class="num-col">Cant.</th><th class="num-col">Precio unit.</th><th class="num-col">Total</th>
    </tr>
    @foreach($p->detalles as $l)
      <tr>
        <td>{{ $l->psd_tipo === 'MANO_OBRA' ? 'Mano de obra' : 'Repuesto' }}</td>
        <td>{{ $l->psd_descri }}</td>
        <td class="num-col">{{ rtrim(rtrim(number_format((float) $l->psd_cantid, 2, ',', '.'), '0'), ',') }}</td>
        <td class="num-col">{{ $gs($l->psd_precio) }}</td>
        <td class="num-col">{{ $gs($l->psd_total) }}</td>
      </tr>
    @endforeach
  </table>

  <table class="totales">
    <tr><td>IVA incluido</td><td class="num-col">{{ $gs($p->pse_impues) }}</td></tr>
    @if($p->pse_versio === 1 && (float) $s->sse_cosret > 0)
      <tr><td>Retiro a domicilio</td><td class="num-col">{{ $gs($s->sse_cosret) }}</td></tr>
    @endif
    <tr class="total"><td>TOTAL DEL PRESUPUESTO</td><td class="num-col">{{ $gs($p->pse_total) }}</td></tr>
  </table>

  @if($p->pse_obs)
    <p><strong>Observaciones:</strong> {{ $p->pse_obs }}</p>
  @endif

  <table class="datos" style="margin-top:10px">
    <tr><td class="et">Válido hasta</td><td><strong>{{ $p->pse_fecven?->format('d/m/Y') ?? '—' }}</strong></td></tr>
    @if($p->pse_respon)
      <tr><td class="et">Respuesta del cliente</td><td>
        {{ $p->estado?->epr_descri }} el {{ $p->pse_respon->format('d/m/Y H:i') }}
        {{ \App\Models\Servicio\PresupuestoServicio::ETIQUETAS_RESPUESTA[$p->pse_canres] ?? '' }}
        @if($p->pse_motrec) — Motivo: {{ $p->pse_motrec }} @endif
      </td></tr>
    @endif
  </table>

  <h2>Condiciones</h2>
  <div class="pie" style="margin-top:0; font-size:10px; color:#444;">
    Precios en guaraníes con IVA incluido. Si el presupuesto no se responde antes de la fecha de validez, vence y el equipo
    se devuelve sin reparar. Al aprobar, los repuestos se reservan; si alguno no tiene stock se adquiere y puede demorar la
    reparación. Si durante la reparación surge un trabajo no presupuestado, se emite un presupuesto complementario que
    requiere una nueva aprobación. Si se rechaza, el equipo se devuelve sin reparar.
  </div>

  @if($codigo === 'ENVIADO')
    <p style="margin-top:14px">
      Decisión del cliente: &nbsp; [ &nbsp; ] APRUEBO &nbsp;&nbsp;&nbsp; [ &nbsp; ] RECHAZO — Motivo: ____________________________
    </p>
  @endif

  <table class="firmas"><tr>
    <td><div class="linea">Firma del cliente</div></td>
    <td><div class="linea">Aclaración y C.I.</div></td>
  </tr></table>

  <div class="pie">Documento de uso interno, sin validez fiscal. La factura se emite al momento del cobro.</div>
</body></html>
