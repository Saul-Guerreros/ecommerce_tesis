import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import {
  SolicitudFiltros, SolicitudesConfig, SolicitudesPaginadas, SolicitudServicioDetalle,
} from '../models/solicitud-servicio.model';
import {
  ClienteMostrador, ClienteRapido, LineaGarantia, MostradorConfig, MostradorRegistrado, ServicioReclamable,
} from '../models/mostrador.model';
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

  // ---------- Ingreso presencial (mostrador) ----------

  mostradorConfig(): Observable<MostradorConfig> {
    return this.http.get<MostradorConfig>(`${this.apiUrl}/mostrador/config`);
  }

  buscarClientes(q: string): Observable<{ data: ClienteMostrador[] }> {
    return this.http.get<{ data: ClienteMostrador[] }>(`${this.apiUrl}/mostrador/clientes`, { params: { q } });
  }

  crearCliente(datos: ClienteRapido): Observable<{ message: string; data: ClienteMostrador }> {
    return this.http.post<{ message: string; data: ClienteMostrador }>(`${this.apiUrl}/mostrador/clientes`, datos);
  }

  completarDocumento(cliId: number, datos: { per_tpdid: number; per_cedula: string }): Observable<{ message: string; data: ClienteMostrador }> {
    return this.http.put<{ message: string; data: ClienteMostrador }>(`${this.apiUrl}/mostrador/clientes/${cliId}/documento`, datos);
  }

  garantiasCliente(cliId: number): Observable<{ data: LineaGarantia[] }> {
    return this.http.get<{ data: LineaGarantia[] }>(`${this.apiUrl}/mostrador/clientes/${cliId}/garantias`);
  }

  /** Paso 3: servicios del cliente con garantía del trabajo vigente y sin reclamo abierto. */
  reclamablesCliente(cliId: number): Observable<{ data: ServicioReclamable[] }> {
    return this.http.get<{ data: ServicioReclamable[] }>(`${this.apiUrl}/mostrador/clientes/${cliId}/reclamables`);
  }

  /** Paso 3: precarga del wizard con ?reclamo=SS-... */
  datosReclamo(numero: string): Observable<{ data: { cliente: ClienteMostrador; original: ServicioReclamable } }> {
    return this.http.get<{ data: { cliente: ClienteMostrador; original: ServicioReclamable } }>(
      `${this.apiUrl}/mostrador/reclamo/${encodeURIComponent(numero)}`,
    );
  }

  /** multipart/form-data (fotos de ingreso). */
  registrarMostrador(datos: FormData): Observable<{ message: string; data: MostradorRegistrado }> {
    return this.http.post<{ message: string; data: MostradorRegistrado }>(`${this.apiUrl}/mostrador`, datos);
  }
}
