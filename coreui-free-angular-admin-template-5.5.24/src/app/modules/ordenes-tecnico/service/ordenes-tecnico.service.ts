import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import {
  DatosGarantiaPayload, FinalizarPayload, LineaPayload, OrdenConfig, OrdenTrabajo, ProductoBusqueda,
  ResolverGarantiaPayload, TableroResponse,
} from '../models/orden-tecnico.model';
import { environment } from '../../../../environments/environment';

type Respuesta = { message: string; data: OrdenTrabajo };

@Injectable({ providedIn: 'root' })
export class OrdenesTecnicoService {
  private apiUrl = `${environment.apiUrl}/admin/servicios/tecnico/ordenes`;

  constructor(private http: HttpClient) {}

  tablero(filtros: { buscar?: string; tecnico?: string } = {}): Observable<TableroResponse> {
    let params = new HttpParams();
    if (filtros.buscar) params = params.set('buscar', filtros.buscar);
    if (filtros.tecnico) params = params.set('tecnico', filtros.tecnico);
    return this.http.get<TableroResponse>(this.apiUrl, { params });
  }

  show(sseId: number): Observable<{ data: OrdenTrabajo }> {
    return this.http.get<{ data: OrdenTrabajo }>(`${this.apiUrl}/${sseId}`);
  }

  config(sseId: number): Observable<OrdenConfig> {
    return this.http.get<OrdenConfig>(`${this.apiUrl}/${sseId}/config`);
  }

  iniciar(sseId: number): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${sseId}/iniciar`, {});
  }

  /** multipart/form-data (fotos de diagnóstico). */
  guardarDiagnostico(sseId: number, datos: FormData): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${sseId}/diagnostico`, datos);
  }

  cerrarNoReparable(sseId: number, datos: { cobrar_revision: boolean; ose_proced?: string }): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${sseId}/no-reparable`, datos);
  }

  // ---------- Garantía de compra: gestión con la empresa responsable ----------

  iniciarGarantia(sseId: number): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${sseId}/garantia/iniciar`, {});
  }

  guardarGarantia(sseId: number, datos: DatosGarantiaPayload): Observable<Respuesta> {
    return this.http.put<Respuesta>(`${this.apiUrl}/${sseId}/garantia`, datos);
  }

  enviarGarantia(sseId: number, datos: DatosGarantiaPayload & { gpr_fecenv: string }): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${sseId}/garantia/enviar`, datos);
  }

  resolverGarantia(sseId: number, datos: ResolverGarantiaPayload): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${sseId}/garantia/resolver`, datos);
  }

  verClave(sseId: number): Observable<{ data: { clave: string | null } }> {
    return this.http.get<{ data: { clave: string | null } }>(`${this.apiUrl}/${sseId}/clave`);
  }

  // ---------- Presupuesto (T3) ----------

  buscarProductos(sseId: number, buscar: string): Observable<{ data: ProductoBusqueda[]; meta: { deposito: string | null } }> {
    return this.http.get<{ data: ProductoBusqueda[]; meta: { deposito: string | null } }>(
      `${this.apiUrl}/${sseId}/productos`, { params: new HttpParams().set('buscar', buscar) });
  }

  guardarPresupuesto(sseId: number, datos: { lineas: LineaPayload[]; pse_obs?: string }): Observable<Respuesta> {
    return this.http.put<Respuesta>(`${this.apiUrl}/${sseId}/presupuesto`, datos);
  }

  enviarPresupuesto(sseId: number): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${sseId}/presupuesto/enviar`, {});
  }

  // ---------- Ejecución (T4) ----------

  iniciarReparacion(sseId: number): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${sseId}/reparacion/iniciar`, {});
  }

  guardarRepuestos(sseId: number, repuestos: Record<number, number>): Observable<Respuesta> {
    return this.http.put<Respuesta>(`${this.apiUrl}/${sseId}/repuestos`, { repuestos });
  }

  finalizar(sseId: number, datos: FinalizarPayload): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${sseId}/finalizar`, datos);
  }

  abrirComplementario(sseId: number): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${sseId}/presupuesto/complementario`, {});
  }

  descartarComplementario(sseId: number): Observable<Respuesta> {
    return this.http.delete<Respuesta>(`${this.apiUrl}/${sseId}/presupuesto/complementario`);
  }
}
