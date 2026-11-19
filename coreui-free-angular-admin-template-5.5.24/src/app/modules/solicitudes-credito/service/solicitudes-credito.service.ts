import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

import {
  SolicitudCreditoFiltros, SolicitudesCreditoConfig, SolicitudesCreditoPaginadas,
} from '../models/solicitud-credito.model';

import {
  DecisionPayload, PlanDisponible, RespuestaSolicitud, SolicitudCreditoDetalle,
} from '../models/solicitud-credito-detalle.model';

import { environment } from '../../../../environments/environment';

/**
 * Sin headers manuales: el token JWT lo agrega auth.interceptor.ts,
 * registrado globalmente en app.config.ts.
 */
@Injectable({ providedIn: 'root' })
export class SolicitudesCreditoService {
  private apiUrl = `${environment.apiUrl}/admin/credito/solicitudes`;

  constructor(private http: HttpClient) {}

  // ---------- Bandeja ----------

  list(filtros: SolicitudCreditoFiltros): Observable<SolicitudesCreditoPaginadas> {
    return this.http.get<SolicitudesCreditoPaginadas>(this.apiUrl, {
      params: this.construirParams(filtros),
    });
  }

  config(): Observable<SolicitudesCreditoConfig> {
    return this.http.get<SolicitudesCreditoConfig>(`${this.apiUrl}/config`);
  }

  // ---------- Detalle ----------

  show(id: number): Observable<{ data: SolicitudCreditoDetalle }> {
    return this.http.get<{ data: SolicitudCreditoDetalle }>(`${this.apiUrl}/${id}`);
  }

  planes(id: number, detalleId: number): Observable<{ data: PlanDisponible[] }> {
    return this.http.get<{ data: PlanDisponible[] }>(
      `${this.apiUrl}/${id}/detalle/${detalleId}/planes`,
    );
  }

  // ---------- Acciones ----------

  /** PENDIENTE -> EN_REVISION. Sin cuerpo: el estado destino es único. */
  tomarEnEvaluacion(id: number): Observable<RespuestaSolicitud> {
    return this.http.patch<RespuestaSolicitud>(`${this.apiUrl}/${id}/evaluar`, {});
  }

  guardarObservaciones(id: number, observaciones: string | null): Observable<RespuestaSolicitud> {
    return this.http.put<RespuestaSolicitud>(`${this.apiUrl}/${id}/observaciones`, { observaciones });
  }

  cambiarPlan(id: number, detalleId: number, cuotas: number): Observable<RespuestaSolicitud> {
    return this.http.patch<RespuestaSolicitud>(
      `${this.apiUrl}/${id}/detalle/${detalleId}/plan`,
      { cuotas },
    );
  }

  fijarCronograma(id: number, fechaPrimeraCuota: string): Observable<RespuestaSolicitud> {
    return this.http.put<RespuestaSolicitud>(`${this.apiUrl}/${id}/cronograma`, {
      fecha_primera_cuota: fechaPrimeraCuota,
    });
  }

  decidir(id: number, payload: DecisionPayload): Observable<RespuestaSolicitud> {
    return this.http.post<RespuestaSolicitud>(`${this.apiUrl}/${id}/decision`, payload);
  }

  // ---------- Auxiliar ----------

  /** Omite los filtros vacíos: una query con parámetros nulos ensucia el log del servidor. */
  private construirParams(filtros: SolicitudCreditoFiltros): HttpParams {
    let params = new HttpParams();

    Object.entries(filtros).forEach(([clave, valor]) => {
      if (valor !== null && valor !== undefined && valor !== '') {
        params = params.set(clave, String(valor));
      }
    });

    return params;
  }
}