import { HttpClient } from '@angular/common/http';
import { Injectable, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import {
  AceptarOfertaRequest,
  ConfigConfirmacionResponse,
  ConfigCreditoResponse,
  GuardarLaboralRequest,
  GuardarPersonalRequest,
  GuardarReferenciaRequest,
  RespuestaOfertaResponse,
  SolicitudCreditoGenerada,
  SolicitudGeneradaResponse,
  SolicitudLaboral,
  SolicitudPersonal,
  SolicitudReferencia,
  DisponibilidadCreditoResponse,
  CheckoutCreditoContexto,
  ConfirmarCheckoutCreditoRequest,
  PedidoCreditoCreado,
} from '../models/credito.model';

/**
 * Servicio HTTP del wizard de Solicitud de Crédito. Mismo patrón que
 * CheckoutService: signals para el estado del borrador en curso +
 * métodos que pegan contra /tienda/credito/*.
 *
 * También concentra la respuesta del cliente a la oferta aprobada y el
 * contador de ofertas pendientes que muestra el menú de cuenta.
 */
@Injectable({ providedIn: 'root' })
export class CreditoService {
  private readonly URL = `${URL_SERVICIOS}/tienda/credito`;

  readonly personal = signal<SolicitudPersonal | null>(null);
  readonly laboral = signal<SolicitudLaboral | null>(null);
  readonly referencias = signal<SolicitudReferencia[] | null>(null);

  /** Ofertas aprobadas esperando respuesta del cliente (número rojo del menú). */
  readonly ofertasPendientes = signal(0);

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

  // ---------- Disponibilidad (antes de abrir el wizard) ----------

  disponibilidad(): Observable<DisponibilidadCreditoResponse> {
    return this.http.get<DisponibilidadCreditoResponse>(`${this.URL}/disponibilidad`);
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

  // ---------- Respuesta a la oferta aprobada ----------

  aceptar(id: number, datos: AceptarOfertaRequest): Observable<RespuestaOfertaResponse> {
    return this.http
      .post<RespuestaOfertaResponse>(`${this.URL}/solicitudes/${id}/aceptar`, datos)
      .pipe(tap(() => this.cargarPendientes()));
  }

  rechazar(id: number): Observable<RespuestaOfertaResponse> {
    return this.http
      .post<RespuestaOfertaResponse>(`${this.URL}/solicitudes/${id}/rechazar`, {})
      .pipe(tap(() => this.cargarPendientes()));
  }

  // ---------- Checkout de la solicitud aceptada ----------

  checkoutContexto(id: number): Observable<{ data: CheckoutCreditoContexto }> {
    return this.http.get<{ data: CheckoutCreditoContexto }>(`${this.URL}/solicitudes/${id}/checkout`);
  }

  confirmarCheckout(
    id: number,
    datos: ConfirmarCheckoutCreditoRequest,
  ): Observable<{ message: string; data: PedidoCreditoCreado }> {
    return this.http
      .post<{ message: string; data: PedidoCreditoCreado }>(`${this.URL}/solicitudes/${id}/checkout/confirmar`, datos)
      .pipe(tap(() => this.cargarPendientes()));
  }

  // ---------- Contador de ofertas pendientes (menú de cuenta) ----------

  cargarPendientes(): void {
    this.http
      .get<{ data: { cantidad: number } }>(`${this.URL}/solicitudes/pendientes`)
      .subscribe({
        next: (resp) => this.ofertasPendientes.set(resp.data.cantidad),
        // Es un aviso secundario: si falla, no se interrumpe la navegación.
        error: () => {},
      });
  }

  limpiarPendientes(): void {
    this.ofertasPendientes.set(0);
  }

  /** Limpia el estado en memoria. Se llama tras generar() o al abandonar el wizard. */
  reiniciar(): void {
    this.personal.set(null);
    this.laboral.set(null);
    this.referencias.set(null);
  }
}