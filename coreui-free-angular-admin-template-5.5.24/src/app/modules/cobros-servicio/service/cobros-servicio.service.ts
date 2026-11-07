import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, shareReplay } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { CobroConfig, CobroDetalle, CobrosPaginado, MedioDeclarado, RespuestaCobro } from '../models/cobro-servicio.model';
import { FacturaServicio, GuardarFacturacion, OpcionesFacturacion, TipoFactura } from '../models/factura-servicio.model';

/**
 * Cobro del servicio técnico. Ciclo comprobante -> factura -> cobro:
 *   validarComprobante -> declararAnticipo / generarFacturaAnticipo / generarFactura -> registrarCobro
 * Devolución del anticipo: acreditarAnticipo (NC) -> registrarDevolucion.
 */
@Injectable({ providedIn: 'root' })
export class CobrosServicioService {
  private apiUrl = `${environment.apiUrl}/admin/servicios/cobros`;

  /** Catálogos (métodos, motivos, sucursales, permisos): una sola petición compartida. */
  private config$?: Observable<{ data: CobroConfig }>;

  constructor(private http: HttpClient) {}

  config(): Observable<{ data: CobroConfig }> {
    this.config$ ??= this.http.get<{ data: CobroConfig }>(`${this.apiUrl}/config`).pipe(shareReplay(1));
    return this.config$;
  }

  listar(filtros: Record<string, string | number | null | undefined>): Observable<CobrosPaginado> {
    let params = new HttpParams();
    Object.entries(filtros).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') params = params.set(k, String(v));
    });
    return this.http.get<CobrosPaginado>(this.apiUrl, { params });
  }

  detalle(sseId: number): Observable<{ data: CobroDetalle }> {
    return this.http.get<{ data: CobroDetalle }>(`${this.apiUrl}/${sseId}`);
  }

  /** La ruta exige token: el comprobante se trae como Blob. */
  comprobante(sseId: number, spaId: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${sseId}/pagos/${spaId}/comprobante`, { responseType: 'blob' });
  }

  // ---------- 1. Comprobante ----------

  validarComprobante(sseId: number, spaId: number, datos: { confirma_monto: boolean; observacion?: string | null }): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/pagos/${spaId}/comprobante/validar`, datos);
  }

  rechazar(sseId: number, spaId: number, datos: Record<string, unknown>): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/pagos/${spaId}/rechazar`, datos);
  }

  // ---------- 2. Facturas ----------

  /** Mostrador: medios del anticipo + factura de anticipo en una operación. */
  declararAnticipo(sseId: number, pagos: MedioDeclarado[]): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/anticipo`, { pagos });
  }

  /** Anticipo web ya validado. */
  generarFacturaAnticipo(sseId: number): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/anticipo/factura/generar`, {});
  }

  /** Final. Sin medios: usa los pagos web ya validados. */
  generarFactura(sseId: number, pagos: MedioDeclarado[]): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/factura/generar`, pagos.length ? { pagos } : {});
  }

  // ---------- 3. Cobro ----------

  /** multipart: puede llevar el comprobante del mostrador. */
  registrarCobro(sseId: number, spaId: number, datos: FormData): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/pagos/${spaId}/cobrar`, datos);
  }

  anularCobro(sseId: number, cobId: number, motivo: string): Observable<RespuestaCobro> {
    return this.http.patch<RespuestaCobro>(`${this.apiUrl}/${sseId}/cobros/${cobId}/anular`, { motivo });
  }

  /** A5: cobro del pago pendiente + entrega en una transacción (multipart). */
  cobrarYEntregar(sseId: number, datos: FormData): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${sseId}/cobrar-entregar`, datos);
  }

  // ---------- Devolución del anticipo ----------

  acreditarAnticipo(sseId: number, datos: { monto?: number | null; observacion?: string | null }): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/anticipo/nota-credito`, datos);
  }

  registrarDevolucion(sseId: number, ncvId: number, datos: Record<string, unknown>): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/anticipo/notas-credito/${ncvId}/devoluciones`, datos);
  }

  anularDevolucion(sseId: number, devId: number, motivo: string): Observable<RespuestaCobro> {
    return this.http.patch<RespuestaCobro>(`${this.apiUrl}/${sseId}/anticipo/devoluciones/${devId}/anular`, { motivo });
  }

  liberarAnticipo(sseId: number, motivo: string): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/factura/anticipo/liberar`, { motivo });
  }

  // ---------- Datos fiscales ----------

  facturacion(sseId: number): Observable<{ data: OpcionesFacturacion }> {
    return this.http.get<{ data: OpcionesFacturacion }>(`${this.apiUrl}/${sseId}/facturacion`);
  }

  guardarFacturacion(sseId: number, datos: GuardarFacturacion): Observable<{ message: string; data: OpcionesFacturacion }> {
    return this.http.put<{ message: string; data: OpcionesFacturacion }>(`${this.apiUrl}/${sseId}/facturacion`, datos);
  }

  // ---------- Facturas (final o anticipo) ----------

  private facturaUrl(sseId: number, tipo: TipoFactura): string {
    return tipo === 'ANTICIPO' ? `${this.apiUrl}/${sseId}/anticipo/factura` : `${this.apiUrl}/${sseId}/factura`;
  }

  factura(sseId: number, tipo: TipoFactura = 'VENTA'): Observable<{ data: FacturaServicio | null }> {
    return this.http.get<{ data: FacturaServicio | null }>(this.facturaUrl(sseId, tipo));
  }

  kudeFactura(sseId: number, tipo: TipoFactura = 'VENTA'): Observable<Blob> {
    return this.http.get(`${this.facturaUrl(sseId, tipo)}/kude`, { responseType: 'blob' });
  }

  xmlFactura(sseId: number, tipo: TipoFactura = 'VENTA'): Observable<Blob> {
    return this.http.get(`${this.facturaUrl(sseId, tipo)}/xml`, { responseType: 'blob' });
  }

  reintentarFactura(sseId: number, tipo: TipoFactura = 'VENTA'): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.facturaUrl(sseId, tipo)}/reintentar`, {});
  }

  reenviarFactura(sseId: number, datos: { canal?: string; destino?: string | null }, tipo: TipoFactura = 'VENTA'): Observable<{ message: string; data: FacturaServicio | null }> {
    return this.http.post<{ message: string; data: FacturaServicio | null }>(`${this.facturaUrl(sseId, tipo)}/reenviar`, datos);
  }

  anularFactura(sseId: number, motivo: string, tipo: TipoFactura = 'VENTA'): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.facturaUrl(sseId, tipo)}/anular`, { motivo });
  }

  // ---------- Utilidades ----------

  descargarBlob(blob: Blob, nombre: string): void {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nombre;
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
  }

  abrirBlob(blob: Blob): void {
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
  }

  /** Primer mensaje de un 422 de Laravel, o el message del error. */
  static mensajeError(err: any, defecto: string): string {
    const errores = err?.error?.errors as Record<string, string[]> | undefined;
    return errores ? Object.values(errores)[0][0] : err?.error?.message ?? defecto;
  }

  /**
   * FormData desde un objeto plano: arrays como clave[] y objetos anidados
   * como padre[hijo], el formato que Laravel interpreta. Omite vacíos.
   */
  static formData(datos: Record<string, unknown>, fd = new FormData(), prefijo = ''): FormData {
    Object.entries(datos).forEach(([k, v]) => {
      const clave = prefijo ? `${prefijo}[${k}]` : k;
      if (v === null || v === undefined || v === '') return;
      if (v instanceof File) fd.append(clave, v);
      else if (Array.isArray(v)) v.forEach((x) => fd.append(`${clave}[]`, String(x)));
      else if (typeof v === 'object') CobrosServicioService.formData(v as Record<string, unknown>, fd, clave);
      else if (typeof v === 'boolean') fd.append(clave, v ? '1' : '0');   // la regla 'boolean' no acepta "true"
      else fd.append(clave, String(v));
    });
    return fd;
  }
}