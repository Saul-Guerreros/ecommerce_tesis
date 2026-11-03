import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import {
  ConfigPorFactura, NotaCreditoVenta, NotaCreditoVentaConfig,
  NotaCreditoVentaFiltros, NotaCreditoVentaInput,
} from '../models/nota-credito-venta.model';
import { environment } from '../../../../environments/environment';

/**
 * Dos prefijos, una sola tabla:
 *   /admin/notas-credito-venta      → el módulo propio (listado completo)
 *   /admin/facturas-venta/{id}/...  → el atajo dentro del detalle de la factura
 *
 * Sin update(): un comprobante fiscal emitido no se edita, se anula.
 * De la NC electrónica se ve el estado, se bajan el KuDE y el XML y se
 * reintenta una declaración pendiente.
 */
@Injectable({ providedIn: 'root' })
export class NotasCreditoVentaService {
  private apiUrl     = `${environment.apiUrl}/admin/notas-credito-venta`;
  private facturaUrl = `${environment.apiUrl}/admin/facturas-venta`;

  constructor(private http: HttpClient) {}

  getAll(filtros: NotaCreditoVentaFiltros = {}): Observable<NotaCreditoVenta[]> {
    let params = new HttpParams();
    if (filtros.search)    params = params.set('search', filtros.search);
    if (filtros.estado)    params = params.set('estado', filtros.estado);
    if (filtros.fe_estado) params = params.set('fe_estado', filtros.fe_estado);
    if (filtros.motivo)    params = params.set('motivo', filtros.motivo);
    if (filtros.sucursal)  params = params.set('sucursal', filtros.sucursal);
    if (filtros.desde)     params = params.set('desde', filtros.desde);
    if (filtros.hasta)     params = params.set('hasta', filtros.hasta);

    return this.http.get<{ data: NotaCreditoVenta[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  getConfig(): Observable<NotaCreditoVentaConfig> {
    return this.http.get<NotaCreditoVentaConfig>(`${this.apiUrl}/config`);
  }

  getById(id: number): Observable<NotaCreditoVenta> {
    return this.http.get<{ data: NotaCreditoVenta }>(`${this.apiUrl}/${id}`)
      .pipe(map((res) => res.data));
  }

  create(nota: NotaCreditoVentaInput): Observable<any> {
    return this.http.post(this.apiUrl, nota);
  }

  anular(id: number, motivo?: string | null): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/anular`, { motivo: motivo ?? null });
  }

  // ---------- Atajo desde la factura ----------

  getConfigPorFactura(venId: number): Observable<ConfigPorFactura> {
    return this.http.get<ConfigPorFactura>(`${this.facturaUrl}/${venId}/notas-credito/config`);
  }

  getPorFactura(venId: number): Observable<NotaCreditoVenta[]> {
    return this.http.get<{ data: NotaCreditoVenta[] }>(`${this.facturaUrl}/${venId}/notas-credito`)
      .pipe(map((res) => res.data));
  }

  // ---------- Nota de crédito electrónica ----------

  /** El KuDE viaja con el JWT (interceptor): se pide como Blob y no con un <a href>. */
  kude(id: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${id}/fe/kude`, { responseType: 'blob' });
  }

  xml(id: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${id}/fe/xml`, { responseType: 'blob' });
  }

  /** Solo encola el reintento (202): el estado se actualiza en unos segundos. */
  reintentarFe(id: number): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${id}/fe/reintentar`, {});
  }

  /** Abre el PDF en otra pestaña; si el navegador bloquea la ventana, lo descarga. */
  abrirPdf(blob: Blob, nombre: string): void {
    const url = URL.createObjectURL(blob);
    const ventana = window.open(url, '_blank');
    if (!ventana) {
      const a = document.createElement('a');
      a.href = url;
      a.download = nombre;
      a.click();
    }
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
  }

  descargar(blob: Blob, nombre: string): void {
    const url = window.URL.createObjectURL(blob);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = nombre;
    enlace.click();
    window.URL.revokeObjectURL(url);
  }

  /** Un error de una petición con responseType blob llega como Blob: se lee el mensaje de adentro. */
  async mensajeDeError(err: any, defecto: string): Promise<string> {
    if (err?.error instanceof Blob) {
      try {
        return JSON.parse(await err.error.text())?.message ?? defecto;
      } catch {
        return defecto;
      }
    }
    return err?.error?.message ?? defecto;
  }
}