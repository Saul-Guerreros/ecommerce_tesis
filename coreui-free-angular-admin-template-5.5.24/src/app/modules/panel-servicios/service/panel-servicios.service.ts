import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { EntregaConfig, PanelConfig, PanelPaginado, PanelResumen } from '../models/panel-servicio.model';
import { environment } from '../../../../environments/environment';

export type TipoConstancia = 'recepcion' | 'retiro';

@Injectable({ providedIn: 'root' })
export class PanelServiciosService {
  private apiUrl = `${environment.apiUrl}/admin/servicios/panel`;

  constructor(private http: HttpClient) {}

  resumen(): Observable<{ data: PanelResumen }> {
    return this.http.get<{ data: PanelResumen }>(`${this.apiUrl}/resumen`);
  }

  config(): Observable<PanelConfig> {
    return this.http.get<PanelConfig>(`${this.apiUrl}/config`);
  }

  listar(filtros: Record<string, string | number | boolean | null | undefined>): Observable<PanelPaginado> {
    let params = new HttpParams();
    Object.entries(filtros).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') params = params.set(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v));
    });
    return this.http.get<PanelPaginado>(this.apiUrl, { params });
  }

  reasignar(id: number, datos: { rse_tecid: number; motivo: string }): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${id}/reasignar`, datos);
  }

  entregaConfig(id: number): Observable<{ data: EntregaConfig }> {
    return this.http.get<{ data: EntregaConfig }>(`${this.apiUrl}/${id}/entrega`);
  }

  registrarEntrega(id: number, datos: Record<string, unknown>): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${id}/entrega`, datos);
  }

  /** El PDF viaja con el JWT (interceptor), por eso se pide como Blob y no con un <a href>. */
  constancia(id: number, tipo: TipoConstancia): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${id}/constancia/${tipo}`, { responseType: 'blob' });
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
}
