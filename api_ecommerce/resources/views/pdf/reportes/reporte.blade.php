@php use App\Services\Reporte\Exportacion\FormatoReporte as F; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $doc['titulo'] }}</title>
<style>
  @page { margin: 26px 30px 40px; }
  body { font-family: DejaVu Sans, sans-serif; font-size: {{ $doc['orientacion'] === 'landscape' ? '8.5px' : '10px' }}; color: #222; }
  .cab { width: 100%; border-bottom: 2px solid #1e87f0; padding-bottom: 6px; margin-bottom: 8px; }
  .cab td { vertical-align: top; }
  h1 { font-size: 15px; margin: 0 0 2px; }
  .muted { color: #666; }
  .derecha { text-align: right; }
  .filtros { margin: 0 0 10px; padding: 0; list-style: none; color: #444; }
  table.datos { width: 100%; border-collapse: collapse; }
  table.datos th { background: #e9ecef; border: 1px solid #bbb; padding: 4px; text-align: left; }
  table.datos td { border: 1px solid #ddd; padding: 3px 4px; }
  table.datos tr:nth-child(even) td { background: #fafafa; }
  table.datos tfoot td { font-weight: bold; background: #f2f2f2; border-top: 2px solid #999; }
  .num { text-align: right; white-space: nowrap; }
  .vacio { padding: 20px; text-align: center; color: #777; border: 1px dashed #ccc; }
  .pie { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 8px; color: #888; }
</style>
</head>
<body>

<table class="cab">
  <tr>
    <td>
      <h1>{{ $doc['titulo'] }}</h1>
      <div>{{ $doc['empresa'] }}@if($doc['ruc']) — RUC {{ $doc['ruc'] }}@endif</div>
    </td>
    <td class="derecha muted">
      Generado: {{ $doc['generado'] }}<br>
      @if($doc['usuario']) Por: {{ $doc['usuario'] }} @endif
    </td>
  </tr>
</table>

<ul class="filtros">
  @foreach($doc['filtros'] as $linea)
    <li>{{ $linea }}</li>
  @endforeach
  <li>{{ count($doc['filas']) }} registros</li>
</ul>

@if(count($doc['filas']) === 0)
  <div class="vacio">Sin datos para los filtros seleccionados.</div>
@else
  <table class="datos">
    <thead>
      <tr>
        @foreach($doc['columnas'] as $c)
          <th class="{{ in_array($c['tipo'], F::NUMERICOS) ? 'num' : '' }}">{{ $c['titulo'] }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($doc['filas'] as $fila)
        <tr>
          @foreach($doc['columnas'] as $c)
            <td class="{{ in_array($c['tipo'], F::NUMERICOS) ? 'num' : '' }}">
              {{ F::valor($fila[$c['campo']] ?? null, $c['tipo']) }}
            </td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
    @if($doc['totales'])
      <tfoot>
        <tr>
          @foreach($doc['columnas'] as $i => $c)
            <td class="{{ $i > 0 && in_array($c['tipo'], F::NUMERICOS) ? 'num' : '' }}">
              {{ $i === 0 ? 'Total' : ($doc['totales'][$i] === null ? '' : F::valor($doc['totales'][$i], $c['tipo'])) }}
            </td>
          @endforeach
        </tr>
      </tfoot>
    @endif
  </table>
@endif

<div class="pie">
  {{ $doc['empresa'] }} · Documento de uso interno · Montos en guaraníes (PYG)
</div>

</body>
</html>