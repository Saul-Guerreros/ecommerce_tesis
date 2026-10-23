<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Constancia de retiro {{ $s->sse_numero }}</title>
@include('pdf.servicio._estilos')
</head>
<body>
  @php
    $e = $s->entrega;
    $o = $s->ordenServicio;
  @endphp
  <table class="cab"><tr>
    <td>
      <h1>{{ $empresa?->emp_razonsocial ?? 'Z Informática' }}</h1>
      <div class="muted">RUC {{ $empresa?->emp_ruc }} · Tel. {{ $empresa?->emp_telef }}</div>
    </td>
    <td class="derecha">
      <div>CONSTANCIA DE RETIRO</div>
      <div class="num">{{ $s->sse_numero }}</div>
      <div class="muted">{{ $o?->ose_numero }} · {{ $e->ese_fecha->format('d/m/Y H:i') }}</div>
    </td>
  </tr></table>

  <h2>Equipo</h2>
  <table class="datos">
    <tr><td class="et">Tipo / marca / modelo</td><td>{{ $s->tipoDispositivo?->tdi_descri }} {{ $s->sse_marca }} {{ $s->sse_modelo }}</td></tr>
    <tr><td class="et">N.º de serie</td><td>{{ $s->sse_nroser ?: '—' }}</td></tr>
    <tr><td class="et">Titular</td><td>{{ $s->cliente?->persona?->nombre_completo }} ({{ $s->cliente?->persona?->tipoDocumento?->tpd_codigo === 'PAS' ? 'Pasaporte' : 'C.I.' }} {{ $s->cliente?->persona?->per_cedula }})</td></tr>
  </table>

  <h2>Trabajo realizado</h2>
  <table class="datos">
    <tr><td class="et">Resultado</td><td>{{ ['REPARADO' => 'Reparado', 'PARCIAL' => 'Reparación parcial', 'NO_REPARABLE' => 'Devuelto sin reparar'][$o?->ose_result] ?? '—' }}</td></tr>
    <tr><td class="et">Detalle</td><td>{{ $o?->ose_proced }}</td></tr>
    <tr><td class="et">Garantía del trabajo</td>
      <td>{{ $vence ? $o->ose_gardia . ' días — válida hasta el ' . $vence->format('d/m/Y') : 'Sin garantía' }}</td></tr>
    <tr><td class="et">Total del servicio</td><td>Gs. {{ number_format((float) $s->sse_total, 0, ',', '.') }}</td></tr>
  </table>

  <h2>Entrega</h2>
  <table class="datos">
    <tr><td class="et">Modalidad</td>
      <td>{{ $e->ese_dirid ? 'Envío a domicilio: ' . $e->direccionTexto() : 'Retiro en sucursal ' . $e->sucursal?->suc_nombre }}</td></tr>
    <tr><td class="et">Recibió</td>
      <td>{{ $e->ese_nombre }} {{ $e->ese_apelli }} — C.I. {{ $e->ese_nrodoc }} {{ $e->ese_titula ? '(titular)' : '(tercero autorizado)' }}</td></tr>
    <tr><td class="et">Entregado por</td><td>{{ $e->usuario?->persona?->nombre_completo }}</td></tr>
  </table>

  <h2>Accesorios devueltos</h2>
  @if(!$s->recepcion || $s->recepcion->accesorios->isEmpty())
    <p>El equipo ingresó sin accesorios.</p>
  @else
    <table class="lista">
      <tr><th>Descripción</th><th style="width:20%">Devuelto</th></tr>
      @foreach($s->recepcion->accesorios as $a)
        <tr><td>{{ $a->rac_descri }}</td><td>{{ $a->rac_devuel ? 'Sí' : 'NO' }}</td></tr>
      @endforeach
    </table>
  @endif
  @if($e->ese_obs)
    <p><strong>Observaciones:</strong> {{ $e->ese_obs }}</p>
  @endif

  <table class="firmas"><tr>
    <td><div class="linea">Firma de quien retira</div></td>
    <td><div class="linea">Firma y sello del taller</div></td>
  </tr></table>

  <div class="pie">
    Con su firma, quien retira declara recibir el equipo y los accesorios indicados en las condiciones descriptas.
    Para reclamar la garantía, ingresá a tu perfil en la tienda. Documento de uso interno, sin validez fiscal.
  </div>
</body></html>
