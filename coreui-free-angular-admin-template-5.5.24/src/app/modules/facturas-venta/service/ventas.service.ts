import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Venta, VentaConfig, VentaFiltros } from '../models/venta.model';
import { environment } from '../../../../environments/environment';

/**
 * Sin create() ni update(): la factura la emite el backend cuando el
 * pedido se cobra o se entrega. Desde el panel solo se consulta y se
 * anula. De la factura electronica se ve el estado, se bajan el KuDE y el
 * XML y se reintenta una declaracion pendiente.
 */
@Injectable({ providedIn: 'root' })
export class VentasService {
  private apiUrl = `${environment.apiUrl}/admin/facturas-venta`;

  constructor(private http: HttpClient) {}

  getAll(filtros: VentaFiltros = {}): Observable<Venta[]> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.estado) params = params.set('estado', filtros.estado);
    if (filtros.fe_estado) params = params.set('fe_estado', filtros.fe_estado);
    if (filtros.desde)  params = params.set('desde', filtros.desde);
    if (filtros.hasta)  params = params.set('hasta', filtros.hasta);

    return this.http.get<{ data: Venta[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<VentaConfig> {
    return this.http.get<VentaConfig>(`${this.apiUrl}/config`);
  }

  getById(id: number): Observable<Venta> {
    return this.http.get<{ data: Venta }>(`${this.apiUrl}/${id}`).pipe(map((res) => res.data));
  }

  anular(id: number, motivo?: string | null): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/anular`, { motivo: motivo ?? null });
  }

  // ---------- Factura electronica ----------

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

  /** Un error de una peticion con responseType blob llega como Blob: se lee el mensaje de adentro. */
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