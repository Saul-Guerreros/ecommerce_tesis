import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, shareReplay } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { CobroConfig, CobroDetalle, CobrosPaginado, RespuestaCobro } from '../models/cobro-servicio.model';
import { FacturaServicio, GuardarFacturacion, OpcionesFacturacion } from '../models/factura-servicio.model';

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

  confirmar(sseId: number, spaId: number, datos: Record<string, unknown>): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/pagos/${spaId}/confirmar`, datos);
  }

  rechazar(sseId: number, spaId: number, datos: Record<string, unknown>): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/pagos/${spaId}/rechazar`, datos);
  }

  /** multipart: puede llevar un comprobante opcional. */
  registrarCobro(sseId: number, datos: FormData): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/cobros`, datos);
  }

  cobrarAlRetirar(sseId: number, spaId: number): Observable<RespuestaCobro> {
    return this.http.post<RespuestaCobro>(`${this.apiUrl}/${sseId}/pagos/${spaId}/cobrar-al-retirar`, {});
  }

  /** A5: cobro del saldo + entrega en una transacción (multipart). */
  cobrarYEntregar(sseId: number, datos: FormData): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${sseId}/cobrar-entregar`, datos);
  }

  // ---------- Facturación del servicio ----------

  /** Opciones CI/RUC del cliente y la elección actual. */
  facturacion(sseId: number): Observable<{ data: OpcionesFacturacion }> {
    return this.http.get<{ data: OpcionesFacturacion }>(`${this.apiUrl}/${sseId}/facturacion`);
  }

  guardarFacturacion(sseId: number, datos: GuardarFacturacion): Observable<{ message: string; data: OpcionesFacturacion }> {
    return this.http.put<{ message: string; data: OpcionesFacturacion }>(`${this.apiUrl}/${sseId}/facturacion`, datos);
  }

  factura(sseId: number): Observable<{ data: FacturaServicio | null }> {
    return this.http.get<{ data: FacturaServicio | null }>(`${this.apiUrl}/${sseId}/factura`);
  }

  /** La ruta exige token: el KuDE y el XML se traen como Blob. */
  kudeFactura(sseId: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${sseId}/factura/kude`, { responseType: 'blob' });
  }

  xmlFactura(sseId: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${sseId}/factura/xml`, { responseType: 'blob' });
  }

  reintentarFactura(sseId: number): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${sseId}/factura/reintentar`, {});
  }

  reenviarFactura(sseId: number, datos: { canal?: string; destino?: string | null }): Observable<{ message: string; data: FacturaServicio | null }> {
    return this.http.post<{ message: string; data: FacturaServicio | null }>(`${this.apiUrl}/${sseId}/factura/reenviar`, datos);
  }

  /** Descarga un Blob con nombre (el XML se guarda, no se abre). */
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
      else fd.append(clave, String(v));
    });
    return fd;
  }
}
