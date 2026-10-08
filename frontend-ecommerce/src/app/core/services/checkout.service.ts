import { HttpClient } from '@angular/common/http';
import { Injectable, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import {
  ConfigConfirmacionResponse,   // <-- nuevo
  ConfigEntregaResponse,
  ConfigFacturacionResponse,
  ConfigPagoResponse,
  EntregaCheckout,
  FacturacionCheckout,
  GuardarEntregaRequest,
  GuardarFacturacionRequest,
  GuardarPagoRequest,
  PagoCheckout,
  PedidoConfirmado,             // <-- nuevo
} from '../models/checkout.model';

@Injectable({ providedIn: 'root' })
export class CheckoutService {
  private readonly URL = `${URL_SERVICIOS}/tienda/checkout`;

  readonly entrega = signal<EntregaCheckout | null>(null);
  readonly facturacion = signal<FacturacionCheckout | null>(null);

  constructor(private http: HttpClient) {}

  configEntrega(): Observable<ConfigEntregaResponse> {
    return this.http
      .get<ConfigEntregaResponse>(`${this.URL}/entrega`)
      .pipe(tap((resp) => this.entrega.set(resp.entrega)));
  }

  guardarEntrega(datos: GuardarEntregaRequest) {
    return this.http
      .post<{ message: string; data: EntregaCheckout }>(`${this.URL}/entrega`, datos)
      .pipe(tap((resp) => this.entrega.set(resp.data)));
  }

  configFacturacion(): Observable<ConfigFacturacionResponse> {
    return this.http
      .get<ConfigFacturacionResponse>(`${this.URL}/facturacion`)
      .pipe(tap((resp) => this.facturacion.set(resp.facturacion)));
  }

  guardarFacturacion(datos: GuardarFacturacionRequest) {
    return this.http
      .post<{ message: string; data: FacturacionCheckout }>(`${this.URL}/facturacion`, datos)
      .pipe(tap((resp) => this.facturacion.set(resp.data)));
  }

  configPago(): Observable<ConfigPagoResponse> {
    return this.http.get<ConfigPagoResponse>(`${this.URL}/pago`);
  }

  /**
   * Un solo endpoint para los dos modos. Va como FormData porque lleva
   * archivos; las claves indexadas pagos[i][campo] son las que Laravel
   * entiende como array de objetos con archivo adentro.
   *
   * conservar = 1 le dice al backend que reutilice el comprobante que ya
   * tenia guardado ese pago: el navegador no puede reenviar un archivo que
   * el usuario no volvio a elegir.
   */
  guardarPago(payload: GuardarPagoRequest) {
    const formData = new FormData();
    formData.append('modo', payload.modo);

    if (payload.modo === 'unico') {
      formData.append('mpa_codigo', payload.mpa_codigo!);
      if (payload.comprobante) formData.append('comprobante', payload.comprobante);
    } else {
      (payload.pagos ?? []).forEach((pago, i) => {
        formData.append(`pagos[${i}][mpa_codigo]`, pago.mpa_codigo);
        formData.append(`pagos[${i}][monto]`, String(pago.monto));
        if (pago.comprobante) formData.append(`pagos[${i}][comprobante]`, pago.comprobante);
        if (pago.conservar && !pago.comprobante) formData.append(`pagos[${i}][conservar]`, '1');
      });
    }

    return this.http.post<{ message: string; data: PagoCheckout }>(`${this.URL}/pago`, formData);
  }


   configConfirmacion(): Observable<ConfigConfirmacionResponse> {
    return this.http.get<ConfigConfirmacionResponse>(`${this.URL}/confirmacion`);
  }

  confirmarPedido(): Observable<{ message: string; data: PedidoConfirmado }> {
    return this.http.post<{ message: string; data: PedidoConfirmado }>(`${this.URL}/confirmar`, {});
  }

  reiniciar(): void {
    this.entrega.set(null);
    this.facturacion.set(null);
  }
}