import { HttpClient } from '@angular/common/http';
import { Injectable, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import {
  ConfigConfirmacionResponse,
  ConfigCreditoResponse,
  GuardarLaboralRequest,
  GuardarPersonalRequest,
  GuardarReferenciaRequest,
  SolicitudCreditoGenerada,
  SolicitudGeneradaResponse,
  SolicitudLaboral,
  SolicitudPersonal,
  SolicitudReferencia,
} from '../models/credito.model';

/**
 * Servicio HTTP del wizard de Solicitud de Crédito. Mismo patrón que
 * CheckoutService: signals para el estado del borrador en curso +
 * métodos que pegan contra /tienda/credito/*.
 */
@Injectable({ providedIn: 'root' })
export class CreditoService {
  private readonly URL = `${URL_SERVICIOS}/tienda/credito`;

  readonly personal = signal<SolicitudPersonal | null>(null);
  readonly laboral = signal<SolicitudLaboral | null>(null);
  readonly referencias = signal<SolicitudReferencia[] | null>(null);

  constructor(private http: HttpClient) {}

  // ---------- Configuración inicial (catálogos + borrador existente) ----------

  config(): Observable<ConfigCreditoResponse> {
    return this.http.get<ConfigCreditoResponse>(`${this.URL}/config`).pipe(
      tap((resp) => {
        this.personal.set(resp.personal);
        this.laboral.set(resp.laboral);
        this.referencias.set(resp.referencias);
      })
    );
  }

  // ---------- Paso 1: Datos personales ----------

  guardarPersonal(datos: GuardarPersonalRequest) {
    return this.http
      .post<{ message: string; data: SolicitudPersonal }>(`${this.URL}/personal`, datos)
      .pipe(tap((resp) => this.personal.set(resp.data)));
  }

  // ---------- Paso 2: Datos laborales ----------

  guardarLaboral(datos: GuardarLaboralRequest) {
    return this.http
      .post<{ message: string; data: SolicitudLaboral }>(`${this.URL}/laboral`, datos)
      .pipe(tap((resp) => this.laboral.set(resp.data)));
  }

  // ---------- Paso 3: Referencias ----------

  guardarReferencias(referencias: GuardarReferenciaRequest[]) {
    return this.http
      .post<{ message: string; data: SolicitudReferencia[] }>(`${this.URL}/referencias`, { referencias })
      .pipe(tap((resp) => this.referencias.set(resp.data)));
  }

  // ---------- Paso 4: Confirmación ----------

  configConfirmacion(): Observable<ConfigConfirmacionResponse> {
    return this.http.get<ConfigConfirmacionResponse>(`${this.URL}/confirmacion`);
  }

  generar(): Observable<SolicitudGeneradaResponse> {
    return this.http.post<SolicitudGeneradaResponse>(`${this.URL}/generar`, { scr_declar: true });
  }

  // ---------- "Mis solicitudes" ----------

  listar(): Observable<{ data: SolicitudCreditoGenerada[] }> {
    return this.http.get<{ data: SolicitudCreditoGenerada[] }>(`${this.URL}/solicitudes`);
  }

  detalle(id: number): Observable<{ data: SolicitudCreditoGenerada }> {
    return this.http.get<{ data: SolicitudCreditoGenerada }>(`${this.URL}/solicitudes/${id}`);
  }

  /** Limpia el estado en memoria. Se llama tras generar() o al abandonar el wizard. */
  reiniciar(): void {
    this.personal.set(null);
    this.laboral.set(null);
    this.referencias.set(null);
  }
}