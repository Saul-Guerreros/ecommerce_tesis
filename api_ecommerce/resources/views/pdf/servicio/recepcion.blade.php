<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Constancia de recepción {{ $s->sse_numero }}</title>
@include('pdf.servicio._estilos')
</head>
<body>
  <table class="cab"><tr>
    <td>
      <h1>{{ $empresa?->emp_razonsocial ?? 'Z Informática' }}</h1>
      <div class="muted">RUC {{ $empresa?->emp_ruc }} · Tel. {{ $empresa?->emp_telef }}</div>
      <div class="muted">{{ $s->sucursal?->suc_nombre }} — {{ $s->sucursal?->suc_direccion }}</div>
    </td>
    <td class="derecha">
      <div>CONSTANCIA DE RECEPCIÓN</div>
      <div class="num">{{ $s->sse_numero }}</div>
      <div class="muted">{{ $s->recepcion->rse_fecha->format('d/m/Y H:i') }}</div>
    </td>
  </tr></table>

  <h2>Cliente</h2>
  <table class="datos">
    <tr><td class="et">Nombre</td><td>{{ $s->cliente?->persona?->nombre_completo }}</td></tr>
    <tr><td class="et">C.I.</td><td>{{ $s->cliente?->persona?->per_cedula ?: '—' }}</td></tr>
  </table>

  <h2>Equipo</h2>
  <table class="datos">
    <tr><td class="et">Tipo / marca / modelo</td><td>{{ $s->tipoDispositivo?->tdi_descri }} {{ $s->sse_marca }} {{ $s->sse_modelo }}</td></tr>
    <tr><td class="et">N.º de serie</td><td>{{ $s->sse_nroser ?: '—' }}</td></tr>
    <tr><td class="et">Servicio</td><td>{{ $s->sse_garant ? 'Garantía' : 'Reparación' }}</td></tr>
    <tr><td class="et">Problema declarado</td><td>{{ $s->sse_proble }}</td></tr>
    <tr><td class="et">Clave de acceso</td><td>{{ $s->recepcion->getRawOriginal('rse_clave') ? 'Registrada (resguardada por el taller)' : 'No informada' }}</td></tr>
  </table>

  <h2>Estado físico al ingreso</h2>
  <div class="caja">{{ $s->recepcion->rse_estfis }}</div>
  @if($s->recepcion->rse_obs)
    <p><strong>Observaciones:</strong> {{ $s->recepcion->rse_obs }}</p>
  @endif
  <p class="muted">Se tomaron {{ $fotos }} fotografía(s) del equipo al momento del ingreso.</p>

  <h2>Accesorios recibidos</h2>
  @if($s->recepcion->accesorios->isEmpty())
    <p>Solo el equipo, sin accesorios.</p>
  @else
    <table class="lista">
      <tr><th style="width:8%">#</th><th>Descripción</th></tr>
      @foreach($s->recepcion->accesorios as $i => $a)
        <tr><td>{{ $i + 1 }}</td><td>{{ $a->rac_descri }}</td></tr>
      @endforeach
    </table>
  @endif

  <table class="datos" style="margin-top:10px">
    <tr><td class="et">Recibido por</td><td>{{ $s->recepcion->recibidoPor?->persona?->nombre_completo }}</td></tr>
    <tr><td class="et">Técnico asignado</td><td>{{ $s->recepcion->tecnico?->persona?->nombre_completo }}</td></tr>
  </table>

  <table class="firmas"><tr>
    <td><div class="linea">Firma del cliente</div></td>
    <td><div class="linea">Firma y sello del taller</div></td>
  </tr></table>

  <div class="pie">
    El diagnóstico se informa mediante presupuesto, que el cliente aprueba o rechaza desde su perfil. Hacé un respaldo de tu
    información: el taller no se responsabiliza por los datos almacenados. Documento de uso interno, sin validez fiscal.
  </div>
</body></html>
