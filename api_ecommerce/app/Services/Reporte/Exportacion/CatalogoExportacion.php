<?php

namespace App\Services\Reporte\Exportacion;

use App\Services\Reporte\FiltrosReporte;
use App\Services\Reporte\InventarioReporteService;
use App\Services\Reporte\VentasReporteService;
use App\Services\Reporte\Exportacion\FormatoReporte as T;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Services\Reporte\ComprasReporteService;
use App\Services\Reporte\ServicioReporteService;
use App\Services\Reporte\ClientesReporteService;
use App\Services\Reporte\PromocionesReporteService;
use App\Services\Reporte\ChatbotReporteService;
/**
 * Definición exportable de cada vista de reporte: título, columnas y
 * origen de datos (el MISMO método que usa la pantalla).
 *
 * Columna: ['campo', 'titulo', 'tipo', 'total'?]
 *   'total' = clave de "totales" que se muestra en la fila final.
 */
class CatalogoExportacion
{
    public function __construct(
        private readonly VentasReporteService $ventas,
        private readonly InventarioReporteService $inventario,
        private readonly ComprasReporteService $compras,
        private readonly ServicioReporteService $servicio,
        private readonly ClientesReporteService $clientes,
        private readonly PromocionesReporteService $promociones,
        private readonly ChatbotReporteService $chatbot,

    ) {
    }

    /**
     * @return array{titulo: string, usa_fechas: bool, orientacion: string, columnas: array, resultado: array}
     */
    public function resolver(string $reporte, string $vista, FiltrosReporte $f, array $extra): array
    {
        $cat   = $extra['categoria_id'] ?? null;
        $marca = $extra['marca_id'] ?? null;

        $def = match ("{$reporte}.{$vista}") {

            // ===================== VENTAS =====================
            'ventas.periodo' => [
                'titulo'   => 'Ventas por período',
                'columnas' => [
                    $this->col('etiqueta', 'Período'),
                    $this->col('facturas', 'Facturas', T::ENTERO, 'facturas'),
                    $this->col('ticket', 'Ticket promedio', T::GS, 'ticket'),
                    $this->col('total', 'Total', T::GS, 'total'),
                ],
                'datos' => fn () => $this->ventas->porPeriodo($f),
            ],
            'ventas.productos' => [
                'titulo'   => 'Ventas por producto',
                'columnas' => [
                    $this->col('codigo', 'Código'),
                    $this->col('producto', 'Producto'),
                    $this->col('categoria', 'Categoría'),
                    $this->col('marca', 'Marca'),
                    ...$this->columnasMargen(),
                ],
                'datos' => fn () => $this->ventas->porProducto($f, $cat, $marca),
            ],
            'ventas.agrupado' => [
                'titulo'   => ($extra['por'] ?? 'categoria') === 'marca' ? 'Ventas por marca' : 'Ventas por categoría',
                'columnas' => [
                    $this->col('nombre', ($extra['por'] ?? 'categoria') === 'marca' ? 'Marca' : 'Categoría'),
                    $this->col('participacion', 'Participación', T::PCT),
                    ...$this->columnasMargen(),
                ],
                'datos' => fn () => $this->ventas->agrupado($f, $extra['por'] ?? 'categoria'),
            ],
            'ventas.metodos-pago' => [
                'titulo'   => 'Ventas por método de pago',
                'columnas' => [
                    $this->col('metodo', 'Método de pago'),
                    $this->col('operaciones', 'Operaciones', T::ENTERO, 'operaciones'),
                    $this->col('participacion', 'Participación', T::PCT),
                    $this->col('total', 'Total', T::GS, 'total'),
                ],
                'datos' => fn () => $this->ventas->porMetodoPago($f),
            ],
            'ventas.ubicacion' => [
                'titulo'   => 'Ventas por ciudad',
                'columnas' => [
                    $this->col('departamento', 'Departamento'),
                    $this->col('ciudad', 'Ciudad'),
                    $this->col('pedidos', 'Pedidos', T::ENTERO, 'pedidos'),
                    $this->col('participacion', 'Participación', T::PCT),
                    $this->col('total', 'Total', T::GS, 'total'),
                ],
                'datos' => fn () => $this->ventas->porUbicacion($f),
            ],

            // ===================== INVENTARIO (fotos actuales) =====================
            'inventario.valorizado' => [
                'titulo'     => 'Stock valorizado',
                'usa_fechas' => false,
                'columnas'   => [
                    $this->col('codigo', 'Código'),
                    $this->col('producto', 'Producto'),
                    $this->col('categoria', 'Categoría'),
                    $this->col('marca', 'Marca'),
                    $this->col('deposito', 'Depósito'),
                    $this->col('cantidad', 'Cantidad', T::NUMERO, 'unidades'),
                    $this->col('costo', 'Costo promedio', T::GS),
                    $this->col('valor', 'Valor', T::GS, 'valor'),
                ],
                'datos' => fn () => $this->inventario->valorizado($f, $cat, $marca),
            ],
            'inventario.critico' => [
                'titulo'     => 'Stock crítico',
                'usa_fechas' => false,
                'columnas'   => [
                    $this->col('nivel', 'Nivel', T::ETIQUETA),
                    $this->col('codigo', 'Código'),
                    $this->col('producto', 'Producto'),
                    $this->col('deposito', 'Depósito'),
                    $this->col('existencia', 'Existencia', T::NUMERO),
                    $this->col('reservado', 'Reservado', T::NUMERO),
                    $this->col('disponible', 'Disponible', T::NUMERO),
                    $this->col('minimo', 'Mínimo', T::NUMERO),
                    $this->col('reponer', 'A reponer', T::NUMERO),
                    $this->col('costo_reposicion', 'Costo reposición', T::GS, 'costo_reposicion'),
                ],
                'datos' => fn () => $this->inventario->critico($f, $cat, $marca),
            ],
            'inventario.sin-movimiento' => [
                'titulo'     => 'Productos sin movimiento',
                'usa_fechas' => false,
                'notas'      => ['Sin ventas en los últimos ' . ($extra['dias'] ?? 90) . ' días'],
                'columnas'   => [
                    $this->col('codigo', 'Código'),
                    $this->col('producto', 'Producto'),
                    $this->col('deposito', 'Depósito'),
                    $this->col('ultima_salida', 'Última salida', T::FECHA),
                    $this->col('ultima_entrada', 'Última entrada', T::FECHA),
                    $this->col('dias', 'Días', T::ENTERO),
                    $this->col('cantidad', 'Cantidad', T::NUMERO, 'unidades'),
                    $this->col('valor', 'Valor', T::GS, 'valor'),
                ],
                'datos' => fn () => $this->inventario->sinMovimiento($f, $extra['dias'] ?? 90, $cat, $marca),
            ],
            'inventario.margen' => [
                'titulo'     => 'Margen por producto (precio vigente vs costo)',
                'usa_fechas' => false,
                'columnas'   => [
                    $this->col('codigo', 'Código'),
                    $this->col('producto', 'Producto'),
                    $this->col('costo', 'Costo promedio', T::GS),
                    $this->col('precio', 'Precio (IVA)', T::GS),
                    $this->col('precio_sin_iva', 'Precio s/IVA', T::GS),
                    $this->col('ganancia_unitaria', 'Ganancia u.', T::GS),
                    $this->col('margen_pct', 'Margen', T::PCT, 'margen_promedio'),
                    $this->col('markup_real', 'Markup real', T::PCT),
                    $this->col('markup_config', 'Markup conf.', T::PCT),
                    $this->col('desactualizado', 'Revisar precio', T::BOOL),
                ],
                'datos' => fn () => $this->inventario->margen($f, $cat, $marca),
            ],

        // ===================== COMPRAS =====================
            'compras.proveedores' => [
                'titulo'   => 'Compras por proveedor',
                'columnas' => [
                    $this->col('proveedor', 'Proveedor'),
                    $this->col('ruc', 'RUC'),
                    $this->col('compras', 'Facturas', T::ENTERO, 'compras'),
                    $this->col('contado', 'Contado', T::GS, 'contado'),
                    $this->col('credito', 'Crédito', T::GS, 'credito'),
                    $this->col('iva', 'IVA', T::GS, 'iva'),
                    $this->col('total', 'Total', T::GS, 'total'),
                    $this->col('participacion', 'Participación', T::PCT),
                    $this->col('ultima_compra', 'Última compra', T::FECHA),
                ],
                'datos' => fn () => $this->compras->porProveedor($f, $extra['proveedor_id'] ?? null),
            ],
            'compras.cxp-vencidas' => [
                'titulo'     => 'Cuentas por pagar vencidas',
                'usa_fechas' => false,
                'columnas'   => [
                    $this->col('proveedor', 'Proveedor'),
                    $this->col('ruc', 'RUC'),
                    $this->col('factura', 'Factura'),
                    $this->col('cuota', 'Cuota'),
                    $this->col('vencimiento', 'Vencimiento', T::FECHA),
                    $this->col('dias_vencido', 'Días vencida', T::ENTERO),
                    $this->col('tramo', 'Tramo'),
                    $this->col('importe', 'Importe', T::GS, 'importe'),
                    $this->col('pagado', 'Pagado', T::GS, 'pagado'),
                    $this->col('saldo', 'Saldo', T::GS, 'saldo'),
                ],
                'datos' => fn () => $this->compras->cuentasVencidas($f, $extra['proveedor_id'] ?? null),
            ],
            'compras.cxp-por-vencer' => [
                'titulo'     => 'Cuentas por pagar por vencer',
                'usa_fechas' => false,
                'notas'      => ['Cuotas que vencen en los próximos ' . ($extra['dias'] ?? 30) . ' días'],
                'columnas'   => [
                    $this->col('proveedor', 'Proveedor'),
                    $this->col('ruc', 'RUC'),
                    $this->col('factura', 'Factura'),
                    $this->col('cuota', 'Cuota'),
                    $this->col('vencimiento', 'Vencimiento', T::FECHA),
                    $this->col('dias_para_vencer', 'Días para vencer', T::ENTERO),
                    $this->col('tramo', 'Tramo'),
                    $this->col('importe', 'Importe', T::GS, 'importe'),
                    $this->col('pagado', 'Pagado', T::GS, 'pagado'),
                    $this->col('saldo', 'Saldo', T::GS, 'saldo'),
                ],
                'datos' => fn () => $this->compras->cuentasPorVencer($f, $extra['dias'] ?? 30, $extra['proveedor_id'] ?? null),
            ],

            // ===================== SERVICIO TÉCNICO =====================
            'servicio.estados' => [
                'titulo'   => 'Solicitudes de servicio por estado',
                'notas'    => ['Solicitudes creadas en el período, según su estado actual'],
                'columnas' => [
                    $this->col('estado', 'Estado'),
                    $this->col('grupo', 'Situación', T::ETIQUETA),
                    $this->col('cantidad', 'Solicitudes', T::ENTERO, 'cantidad'),
                    $this->col('participacion', 'Participación', T::PCT),
                    $this->col('monto', 'Monto', T::GS, 'monto'),
                ],
                'datos' => fn () => $this->servicio->porEstado($f, $extra['tipo_dispositivo_id'] ?? null),
            ],
            'servicio.tecnicos' => [
                'titulo'   => 'Órdenes de servicio por técnico',
                'notas'    => ['Órdenes iniciadas en el período. Efectividad = reparadas / cerradas'],
                'columnas' => [
                    $this->col('tecnico', 'Técnico'),
                    $this->col('asignadas', 'Asignadas', T::ENTERO, 'asignadas'),
                    $this->col('abiertas', 'Abiertas', T::ENTERO, 'abiertas'),
                    $this->col('cerradas', 'Cerradas', T::ENTERO, 'cerradas'),
                    $this->col('reparadas', 'Reparadas', T::ENTERO, 'reparadas'),
                    $this->col('parciales', 'Parciales', T::ENTERO, 'parciales'),
                    $this->col('no_reparables', 'No reparables', T::ENTERO, 'no_reparables'),
                    $this->col('efectividad', 'Efectividad', T::PCT, 'efectividad'),
                    $this->col('horas', 'Horas prom.', T::NUMERO, 'horas'),
                ],
                'datos' => fn () => $this->servicio->porTecnico($f, $extra['tipo_dispositivo_id'] ?? null),
            ],
            'servicio.tiempos' => [
                'titulo'   => 'Tiempo promedio de reparación',
                'notas'    => ['Órdenes cerradas en el período. Taller: inicio a cierre (horas). Total: recepción a entrega (días)'],
                'columnas' => [
                    $this->col('tipo', 'Tipo de dispositivo'),
                    $this->col('ordenes', 'Órdenes', T::ENTERO, 'ordenes'),
                    $this->col('horas_promedio', 'Horas prom.', T::NUMERO, 'horas_promedio'),
                    $this->col('horas_min', 'Horas mín.', T::NUMERO, 'horas_min'),
                    $this->col('horas_max', 'Horas máx.', T::NUMERO, 'horas_max'),
                    $this->col('entregadas', 'Entregadas', T::ENTERO, 'entregadas'),
                    $this->col('dias_total', 'Días totales prom.', T::NUMERO, 'dias_total'),
                ],
                'datos' => fn () => $this->servicio->tiempos($f, $extra['tipo_dispositivo_id'] ?? null),
            ],
            'servicio.fallas' => [
                'titulo'   => 'Fallas frecuentes',
                'notas'    => ['Fallas registradas en diagnósticos del período'],
                'columnas' => [
                    $this->col('codigo', 'Código'),
                    $this->col('falla', 'Falla'),
                    $this->col('aplica', 'Aplica a'),
                    $this->col('ocurrencias', 'Ocurrencias', T::ENTERO, 'ocurrencias'),
                    $this->col('participacion', 'Participación', T::PCT),
                    $this->col('reparadas', 'Reparadas', T::ENTERO, 'reparadas'),
                    $this->col('tasa_reparacion', 'Tasa reparación', T::PCT),
                ],
                'datos' => fn () => $this->servicio->fallas($f, $extra['tipo_dispositivo_id'] ?? null),
            ],
            'servicio.presupuestos' => [
                'titulo'   => 'Presupuestos de servicio por estado',
                'notas'    => ['Tasa de aprobación = aprobados / (aprobados + rechazados)'],
                'columnas' => [
                    $this->col('estado', 'Estado'),
                    $this->col('cantidad', 'Presupuestos', T::ENTERO, 'cantidad'),
                    $this->col('participacion', 'Participación', T::PCT),
                    $this->col('monto', 'Monto', T::GS, 'monto'),
                    $this->col('horas_respuesta', 'Respuesta prom. (h)', T::NUMERO, 'horas_respuesta'),
                ],
                'datos' => fn () => $this->servicio->presupuestos($f, $extra['tipo_dispositivo_id'] ?? null),
            ],

             // ===================== CLIENTES =====================
            'clientes.top' => [
                'titulo'   => 'Top clientes',
                'notas'    => ["Top {$f->limite} clientes por monto comprado en el período"],
                'columnas' => [
                    $this->col('posicion', '#', T::ENTERO),
                    $this->col('cliente', 'Cliente'),
                    $this->col('documento', 'Documento'),
                    $this->col('tipo', 'Tipo'),
                    $this->col('compras', 'Compras', T::ENTERO, 'compras'),
                    $this->col('ticket', 'Ticket prom.', T::GS),
                    $this->col('total', 'Total', T::GS, 'total'),
                    $this->col('participacion', 'Participación', T::PCT, 'concentracion'),
                    $this->col('ultima_compra', 'Última compra', T::FECHA),
                ],
                'datos' => fn () => $this->clientes->top($f, $extra['tipo_cliente_id'] ?? null),
            ],
            'clientes.nuevos' => [
                'titulo'   => 'Clientes nuevos por período',
                'notas'    => ['Registrados = altas de clientes. Compradores nuevos = primera compra concretada en el período'],
                'columnas' => [
                    $this->col('etiqueta', 'Período'),
                    $this->col('registrados', 'Registrados', T::ENTERO, 'registrados'),
                    $this->col('nuevos', 'Compradores nuevos', T::ENTERO, 'nuevos'),
                    $this->col('recurrentes', 'Recurrentes', T::ENTERO, 'recurrentes'),
                    $this->col('activos', 'Clientes que compraron', T::ENTERO, 'activos'),
                ],
                'datos' => fn () => $this->clientes->nuevos($f),
            ],
            'clientes.inactivos' => [
                'titulo'     => 'Clientes inactivos',
                'usa_fechas' => false,
                'notas'      => ['Sin compras en los últimos ' . ($extra['dias'] ?? 90) . ' días'],
                'columnas'   => [
                    $this->col('cliente', 'Cliente'),
                    $this->col('documento', 'Documento'),
                    $this->col('email', 'Email'),
                    $this->col('telefono', 'Teléfono'),
                    $this->col('ultima_compra', 'Última compra', T::FECHA),
                    $this->col('dias', 'Días', T::ENTERO),
                    $this->col('compras', 'Compras', T::ENTERO, 'compras'),
                    $this->col('total', 'Total histórico', T::GS, 'total'),
                ],
                'datos' => fn () => $this->clientes->inactivos($f, $extra['dias'] ?? 90, $extra['tipo_cliente_id'] ?? null),
            ],

            // ===================== PROMOCIONES (configuración) =====================
            'promociones.cupones' => [
                'titulo'       => 'Cupones configurados',
                'usa_fechas'   => false,
                'usa_sucursal' => false,
                'notas'        => ['El uso de cupones no se registra en los pedidos (trabajo futuro)'],
                'columnas'     => [
                    $this->col('codigo', 'Código'),
                    $this->col('tipo_descuento', 'Tipo'),
                    $this->col('descuento_texto', 'Descuento'),
                    $this->col('uso', 'Uso'),
                    $this->col('alcance', 'Alcance'),
                    $this->col('elementos', 'Elementos', T::ENTERO),
                    $this->col('estado', 'Estado'),
                    $this->col('creado', 'Creado', T::FECHA),
                ],
                'datos' => fn () => $this->promociones->cupones(),
            ],
            'promociones.campanas' => [
                'titulo'       => 'Campañas de descuento',
                'usa_sucursal' => false,
                'notas'        => [
                    'Campañas cuya vigencia se superpone con el período',
                    'Las ventas por campaña no se registran en los pedidos (trabajo futuro)',
                ],
                'columnas'     => [
                    $this->col('codigo', 'Código'),
                    $this->col('tipo_campana', 'Campaña'),
                    $this->col('descuento_texto', 'Descuento'),
                    $this->col('alcance', 'Alcance'),
                    $this->col('elementos', 'Elementos', T::ENTERO),
                    $this->col('inicio', 'Inicio', T::FECHA),
                    $this->col('fin', 'Fin', T::FECHA),
                    $this->col('duracion', 'Días', T::ENTERO),
                    $this->col('situacion', 'Situación', T::ETIQUETA),
                ],
                'datos' => fn () => $this->promociones->campanas($f),
            ],
                // ===================== ASISTENTE VIRTUAL =====================
            'chatbot.periodo' => [
                'titulo'       => 'Asistente virtual: conversaciones por período',
                'usa_sucursal' => false,
                'notas'        => ['Derivada = pidió atención humana. Sin respuesta = el asistente no pudo resolver la consulta'],
                'columnas'     => [
                    $this->col('etiqueta', 'Período'),
                    $this->col('conversaciones', 'Conversaciones', T::ENTERO, 'conversaciones'),
                    $this->col('mensajes', 'Mensajes', T::ENTERO, 'mensajes'),
                    $this->col('derivadas', 'Derivadas', T::ENTERO, 'derivadas'),
                    $this->col('tasa_derivacion', '% derivación', T::PCT, 'tasa_derivacion'),
                    $this->col('con_sin_respuesta', 'Con sin respuesta', T::ENTERO, 'con_sin_respuesta'),
                    $this->col('tasa_sin_respuesta', '% sin respuesta', T::PCT, 'tasa_sin_respuesta'),
                    $this->col('tokens', 'Tokens', T::ENTERO, 'tokens'),
                    $this->col('latencia_ms', 'Latencia (ms)', T::ENTERO, 'latencia_ms'),
                ],
                'datos' => fn () => $this->chatbot->porPeriodo($f),
            ],
            'chatbot.temas' => [
                'titulo'       => 'Asistente virtual: temas consultados',
                'usa_sucursal' => false,
                'notas'        => ['Tema = herramienta que el modelo decidió invocar según la intención detectada'],
                'columnas'     => [
                    $this->col('tema', 'Tema'),
                    $this->col('herramienta', 'Herramienta'),
                    $this->col('invocaciones', 'Veces', T::ENTERO, 'invocaciones'),
                    $this->col('participacion', 'Participación', T::PCT),
                    $this->col('conversaciones', 'Conversaciones', T::ENTERO),
                    $this->col('ultima', 'Último uso', T::FECHA),
                ],
                'datos' => fn () => $this->chatbot->temas($f),
            ],
            'chatbot.preguntas' => [
                'titulo'       => 'Asistente virtual: preguntas más frecuentes',
                'usa_sucursal' => false,
                'notas'        => ['Agrupación por texto idéntico (sin distinguir mayúsculas ni espacios)'],
                'columnas'     => [
                    $this->col('posicion', '#', T::ENTERO),
                    $this->col('pregunta', 'Pregunta'),
                    $this->col('veces', 'Veces', T::ENTERO, 'veces'),
                    $this->col('conversaciones', 'Conversaciones', T::ENTERO),
                    $this->col('participacion', '% de mensajes', T::PCT, 'cobertura'),
                    $this->col('ultima', 'Última vez', T::FECHA),
                ],
                'datos' => fn () => $this->chatbot->preguntas($f),
            ],
            'chatbot.sin-respuesta' => [
                'titulo'       => 'Asistente virtual: consultas sin respuesta y derivadas',
                'usa_sucursal' => false,
                'notas'        => [match ($extra['tipo'] ?? 'todos') {
                    'sin_respuesta' => 'Solo consultas sin respuesta',
                    'derivada'      => 'Solo conversaciones derivadas a atención humana',
                    default         => 'Consultas sin respuesta y conversaciones derivadas',
                }],
                'columnas'     => [
                    $this->col('tipo_texto', 'Tipo'),
                    $this->col('fecha', 'Fecha'),
                    $this->col('conversacion', 'Conversación', T::ENTERO),
                    $this->col('pregunta', 'Pregunta del usuario'),
                    $this->col('detalle', 'Respuesta / motivo'),
                ],
                'datos' => fn () => $this->chatbot->sinRespuesta($f, $extra['tipo'] ?? 'todos'),
            ],

            default => throw new NotFoundHttpException('El reporte solicitado no existe.'),
        };

        return [
            'titulo'       => $def['titulo'],
            'usa_fechas'   => $def['usa_fechas'] ?? true,
            'usa_sucursal' => $def['usa_sucursal'] ?? true,
            'notas'        => $def['notas'] ?? [],
            'orientacion'  => count($def['columnas']) > 5 ? 'landscape' : 'portrait',
            'columnas'     => $def['columnas'],
            'resultado'    => ($def['datos'])(),
        ];
    }

    private function columnasMargen(): array
    {
        return [
            $this->col('unidades', 'Unid.', T::NUMERO, 'unidades'),
            $this->col('total', 'Total', T::GS, 'total'),
            $this->col('venta_neta', 'Venta neta', T::GS, 'venta_neta'),
            $this->col('costo', 'Costo', T::GS, 'costo'),
            $this->col('margen', 'Margen', T::GS, 'margen'),
            $this->col('margen_pct', 'Margen %', T::PCT, 'margen_pct'),
        ];
    }

    private function col(string $campo, string $titulo, string $tipo = T::TEXTO, ?string $total = null): array
    {
        return compact('campo', 'titulo', 'tipo', 'total');
    }
}