import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { FiltrosReporte, ReporteConfig, RespuestaReporte, RespuestaTabla } from '../models/reporte.model';
import { FiltrosVentasExtra, MetricasMargen, TotalesCiudad, TotalesMetodo, TotalesPeriodoVentas, VentaAgrupada, VentaCiudad, VentaMetodo, VentaPeriodo, VentaProducto,} from '../models/ventas.model';
import {DashboardDistribucion, DashboardResumen, DashboardTablas, DashboardVentasCompras,} from '../models/dashboard.model';
import { Observable, shareReplay } from 'rxjs';
import {FiltrosInventarioExtra, MargenProducto, StockCritico, StockSinMovimiento, StockValorizado,TotalesCritico, TotalesMargen, TotalesSinMovimiento, TotalesValorizado,} from '../models/inventario.model';
import {  CompraProveedor, CuotaPendiente, FiltrosComprasExtra, TotalesComprasProveedor, TotalesCuentasPagar,
} from '../models/compras.model';
import {
  FallaFrecuente, FiltrosServicioExtra, MetricasTiempo, OrdenesTecnico, PresupuestoPorEstado,
  SolicitudPorEstado, TiempoPorTipo, TotalesEstados, TotalesFallas, TotalesPresupuestos, TotalesTecnicos,
} from '../models/servicio.model';
import {
  ClienteInactivo, ClientesNuevosPeriodo, ClienteTop, FiltrosClientesExtra, TotalesClientesInactivos,
  TotalesClientesNuevos, TotalesTopClientes,
} from '../models/clientes.model';
import { Campana, Cupon, TotalesCampanas, TotalesCupones } from '../models/promociones.model';
import {
  ChatNoResuelta, ChatPeriodo, ChatPregunta, ChatTema, FiltrosChatbotExtra, TotalesChatNoResueltas,
  TotalesChatPeriodo, TotalesChatPreguntas, TotalesChatTemas,
} from '../models/chatbot.model';


/**
 * Cliente de la API de métricas y reportes.
 * Sin headers manuales: el token JWT lo agrega auth.interceptor.ts.
 */
@Injectable({ providedIn: 'root' })
export class ReportesService {
  private readonly apiUrl = `${environment.apiUrl}/admin`;
  private readonly dashboardUrl = `${this.apiUrl}/metricas/dashboard`;

  constructor(private http: HttpClient) {}

  // ---------- Combos de filtros ----------

   /** Combos de filtros. Se piden una sola vez por sesión: todos los reportes comparten la respuesta. */
  private config$?: Observable<ReporteConfig>;

  getConfig(): Observable<ReporteConfig> {
    return this.config$ ??= this.http
      .get<ReporteConfig>(`${this.apiUrl}/reportes/config`)
      .pipe(shareReplay({ bufferSize: 1, refCount: false }));
  }

  // ---------- Dashboard ----------

  dashboardResumen(filtros: FiltrosReporte): Observable<RespuestaReporte<DashboardResumen>> {
    return this.http.get<RespuestaReporte<DashboardResumen>>(
      `${this.dashboardUrl}/resumen`, { params: this.params(filtros) });
  }

  dashboardVentasCompras(filtros: FiltrosReporte): Observable<RespuestaReporte<DashboardVentasCompras>> {
    return this.http.get<RespuestaReporte<DashboardVentasCompras>>(
      `${this.dashboardUrl}/ventas-compras`, { params: this.params(filtros) });
  }

  dashboardDistribucion(filtros: FiltrosReporte): Observable<RespuestaReporte<DashboardDistribucion>> {
    return this.http.get<RespuestaReporte<DashboardDistribucion>>(
      `${this.dashboardUrl}/distribucion`, { params: this.params(filtros) });
  }

  dashboardTablas(filtros: FiltrosReporte): Observable<RespuestaReporte<DashboardTablas>> {
    return this.http.get<RespuestaReporte<DashboardTablas>>(
      `${this.dashboardUrl}/tablas`, { params: this.params(filtros) });
  }

  // ---------- Reporte de ventas ----------

  private readonly ventasUrl = `${this.apiUrl}/reportes/ventas`;

  ventasPeriodo(f: FiltrosReporte): Observable<RespuestaTabla<VentaPeriodo, TotalesPeriodoVentas>> {
    return this.http.get<RespuestaTabla<VentaPeriodo, TotalesPeriodoVentas>>(
      `${this.ventasUrl}/periodo`, { params: this.params(f) });
  }

  ventasProductos(f: FiltrosReporte & FiltrosVentasExtra): Observable<RespuestaTabla<VentaProducto, MetricasMargen>> {
    return this.http.get<RespuestaTabla<VentaProducto, MetricasMargen>>(
      `${this.ventasUrl}/productos`, { params: this.params(f) });
  }

  ventasAgrupado(f: FiltrosReporte & FiltrosVentasExtra): Observable<RespuestaTabla<VentaAgrupada, MetricasMargen>> {
    return this.http.get<RespuestaTabla<VentaAgrupada, MetricasMargen>>(
      `${this.ventasUrl}/agrupado`, { params: this.params(f) });
  }

  ventasMetodosPago(f: FiltrosReporte): Observable<RespuestaTabla<VentaMetodo, TotalesMetodo>> {
    return this.http.get<RespuestaTabla<VentaMetodo, TotalesMetodo>>(
      `${this.ventasUrl}/metodos-pago`, { params: this.params(f) });
  }

  ventasUbicacion(f: FiltrosReporte): Observable<RespuestaTabla<VentaCiudad, TotalesCiudad>> {
    return this.http.get<RespuestaTabla<VentaCiudad, TotalesCiudad>>(
      `${this.ventasUrl}/ubicacion`, { params: this.params(f) });
  }

    // ---------- Reporte de inventario ----------

  private readonly inventarioUrl = `${this.apiUrl}/reportes/inventario`;

  inventarioValorizado(f: FiltrosReporte & FiltrosInventarioExtra): Observable<RespuestaTabla<StockValorizado, TotalesValorizado>> {
    return this.http.get<RespuestaTabla<StockValorizado, TotalesValorizado>>(
      `${this.inventarioUrl}/valorizado`, { params: this.params(f) });
  }

  inventarioCritico(f: FiltrosReporte & FiltrosInventarioExtra): Observable<RespuestaTabla<StockCritico, TotalesCritico>> {
    return this.http.get<RespuestaTabla<StockCritico, TotalesCritico>>(
      `${this.inventarioUrl}/critico`, { params: this.params(f) });
  }

  inventarioSinMovimiento(f: FiltrosReporte & FiltrosInventarioExtra): Observable<RespuestaTabla<StockSinMovimiento, TotalesSinMovimiento>> {
    return this.http.get<RespuestaTabla<StockSinMovimiento, TotalesSinMovimiento>>(
      `${this.inventarioUrl}/sin-movimiento`, { params: this.params(f) });
  }

  inventarioMargen(f: FiltrosReporte & FiltrosInventarioExtra): Observable<RespuestaTabla<MargenProducto, TotalesMargen>> {
    return this.http.get<RespuestaTabla<MargenProducto, TotalesMargen>>(
      `${this.inventarioUrl}/margen`, { params: this.params(f) });
  }

  // ---------- Reporte de compras ----------

  private readonly comprasUrl = `${this.apiUrl}/reportes/compras`;

  comprasProveedores(f: FiltrosReporte & FiltrosComprasExtra): Observable<RespuestaTabla<CompraProveedor, TotalesComprasProveedor>> {
    return this.http.get<RespuestaTabla<CompraProveedor, TotalesComprasProveedor>>(
      `${this.comprasUrl}/proveedores`, { params: this.params(f) });
  }

  comprasVencidas(f: FiltrosReporte & FiltrosComprasExtra): Observable<RespuestaTabla<CuotaPendiente, TotalesCuentasPagar>> {
    return this.http.get<RespuestaTabla<CuotaPendiente, TotalesCuentasPagar>>(
      `${this.comprasUrl}/cxp-vencidas`, { params: this.params(f) });
  }

  comprasPorVencer(f: FiltrosReporte & FiltrosComprasExtra): Observable<RespuestaTabla<CuotaPendiente, TotalesCuentasPagar>> {
    return this.http.get<RespuestaTabla<CuotaPendiente, TotalesCuentasPagar>>(
      `${this.comprasUrl}/cxp-por-vencer`, { params: this.params(f) });
  }
 // ---------- Reporte de clientes ----------

  private readonly clientesUrl = `${this.apiUrl}/reportes/clientes`;

  clientesTop(f: FiltrosReporte & FiltrosClientesExtra): Observable<RespuestaTabla<ClienteTop, TotalesTopClientes>> {
    return this.http.get<RespuestaTabla<ClienteTop, TotalesTopClientes>>(
      `${this.clientesUrl}/top`, { params: this.params(f) });
  }

  clientesNuevos(f: FiltrosReporte & FiltrosClientesExtra): Observable<RespuestaTabla<ClientesNuevosPeriodo, TotalesClientesNuevos>> {
    return this.http.get<RespuestaTabla<ClientesNuevosPeriodo, TotalesClientesNuevos>>(
      `${this.clientesUrl}/nuevos`, { params: this.params(f) });
  }

  clientesInactivos(f: FiltrosReporte & FiltrosClientesExtra): Observable<RespuestaTabla<ClienteInactivo, TotalesClientesInactivos>> {
    return this.http.get<RespuestaTabla<ClienteInactivo, TotalesClientesInactivos>>(
      `${this.clientesUrl}/inactivos`, { params: this.params(f) });
  }

  // ---------- Reporte de promociones ----------

  private readonly promocionesUrl = `${this.apiUrl}/reportes/promociones`;

  promocionesCupones(f: FiltrosReporte): Observable<RespuestaTabla<Cupon, TotalesCupones>> {
    return this.http.get<RespuestaTabla<Cupon, TotalesCupones>>(
      `${this.promocionesUrl}/cupones`, { params: this.params(f) });
  }

  promocionesCampanas(f: FiltrosReporte): Observable<RespuestaTabla<Campana, TotalesCampanas>> {
    return this.http.get<RespuestaTabla<Campana, TotalesCampanas>>(
      `${this.promocionesUrl}/campanas`, { params: this.params(f) });
  }

   // ---------- Reporte del asistente virtual ----------

  private readonly chatbotUrl = `${this.apiUrl}/reportes/chatbot`;

  chatbotPeriodo(f: FiltrosReporte & FiltrosChatbotExtra): Observable<RespuestaTabla<ChatPeriodo, TotalesChatPeriodo>> {
    return this.http.get<RespuestaTabla<ChatPeriodo, TotalesChatPeriodo>>(
      `${this.chatbotUrl}/periodo`, { params: this.params(f) });
  }

  chatbotTemas(f: FiltrosReporte & FiltrosChatbotExtra): Observable<RespuestaTabla<ChatTema, TotalesChatTemas>> {
    return this.http.get<RespuestaTabla<ChatTema, TotalesChatTemas>>(
      `${this.chatbotUrl}/temas`, { params: this.params(f) });
  }

  chatbotPreguntas(f: FiltrosReporte & FiltrosChatbotExtra): Observable<RespuestaTabla<ChatPregunta, TotalesChatPreguntas>> {
    return this.http.get<RespuestaTabla<ChatPregunta, TotalesChatPreguntas>>(
      `${this.chatbotUrl}/preguntas`, { params: this.params(f) });
  }

  chatbotSinRespuesta(f: FiltrosReporte & FiltrosChatbotExtra): Observable<RespuestaTabla<ChatNoResuelta, TotalesChatNoResueltas>> {
    return this.http.get<RespuestaTabla<ChatNoResuelta, TotalesChatNoResueltas>>(
      `${this.chatbotUrl}/sin-respuesta`, { params: this.params(f) });
  }

   // ---------- Reporte de servicio técnico ----------

  private readonly servicioUrl = `${this.apiUrl}/reportes/servicio`;

  servicioEstados(f: FiltrosReporte & FiltrosServicioExtra): Observable<RespuestaTabla<SolicitudPorEstado, TotalesEstados>> {
    return this.http.get<RespuestaTabla<SolicitudPorEstado, TotalesEstados>>(
      `${this.servicioUrl}/estados`, { params: this.params(f) });
  }

  servicioTecnicos(f: FiltrosReporte & FiltrosServicioExtra): Observable<RespuestaTabla<OrdenesTecnico, TotalesTecnicos>> {
    return this.http.get<RespuestaTabla<OrdenesTecnico, TotalesTecnicos>>(
      `${this.servicioUrl}/tecnicos`, { params: this.params(f) });
  }

  servicioTiempos(f: FiltrosReporte & FiltrosServicioExtra): Observable<RespuestaTabla<TiempoPorTipo, MetricasTiempo>> {
    return this.http.get<RespuestaTabla<TiempoPorTipo, MetricasTiempo>>(
      `${this.servicioUrl}/tiempos`, { params: this.params(f) });
  }

  servicioFallas(f: FiltrosReporte & FiltrosServicioExtra): Observable<RespuestaTabla<FallaFrecuente, TotalesFallas>> {
    return this.http.get<RespuestaTabla<FallaFrecuente, TotalesFallas>>(
      `${this.servicioUrl}/fallas`, { params: this.params(f) });
  }

  servicioPresupuestos(f: FiltrosReporte & FiltrosServicioExtra): Observable<RespuestaTabla<PresupuestoPorEstado, TotalesPresupuestos>> {
    return this.http.get<RespuestaTabla<PresupuestoPorEstado, TotalesPresupuestos>>(
      `${this.servicioUrl}/presupuestos`, { params: this.params(f) });
  }

  // ---------- Exportación ----------

  /** Descarga el archivo binario (PDF o Excel) con los filtros aplicados. */
  exportar(reporte: string, vista: string, filtros: object, formato: 'pdf' | 'excel'): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/reportes/${reporte}/${vista}/exportar`, {
      params: this.params({ ...filtros, formato }),
      responseType: 'blob',
    });
  }

  // ---------- Auxiliares ----------

  /** Omite null, undefined y '' para no enviar filtros vacíos (sucursal "todas"). */
    protected params(filtros: object): HttpParams {
    let params = new HttpParams();

    Object.entries(filtros).forEach(([clave, valor]) => {
      if (valor !== null && valor !== undefined && valor !== '') {
        params = params.set(clave, String(valor));
      }
    });

    return params;
  }
}