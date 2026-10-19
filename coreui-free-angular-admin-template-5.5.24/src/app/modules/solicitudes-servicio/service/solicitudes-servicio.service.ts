import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import {
  SolicitudFiltros, SolicitudesConfig, SolicitudesPaginadas, SolicitudServicioDetalle,
} from '../models/solicitud-servicio.model';
import { environment } from '../../../../environments/environment';

type Respuesta = { message: string; data: SolicitudServicioDetalle };

@Injectable({ providedIn: 'root' })
export class SolicitudesServicioService {
  private apiUrl = `${environment.apiUrl}/admin/servicios/solicitudes`;

  constructor(private http: HttpClient) {}

  getAll(filtros: SolicitudFiltros): Observable<SolicitudesPaginadas> {
    let params = new HttpParams();
    Object.entries(filtros).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') params = params.set(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v));
    });
    return this.http.get<SolicitudesPaginadas>(this.apiUrl, { params });
  }

  getConfig(): Observable<SolicitudesConfig> {
    return this.http.get<SolicitudesConfig>(`${this.apiUrl}/config`);
  }

  show(id: number): Observable<{ data: SolicitudServicioDetalle }> {
    return this.http.get<{ data: SolicitudServicioDetalle }>(`${this.apiUrl}/${id}`);
  }

  agendarRetiro(id: number, datos: { sse_fecing: string; coment?: string }): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${id}/agendar-retiro`, datos);
  }

  enTransito(id: number): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${id}/en-transito`, {});
  }

  rechazar(id: number, motivo: string): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${id}/rechazar`, { motivo });
  }

  /** multipart/form-data: el interceptor no fija Content-Type, el navegador arma el boundary. */
  confirmarRecepcion(id: number, datos: FormData): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${id}/recepcion`, datos);
  }
}
