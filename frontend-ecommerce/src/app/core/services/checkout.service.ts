import { HttpClient } from '@angular/common/http';
import { Injectable, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import {
  ConfigEntregaResponse,
  ConfigFacturacionResponse,
  EntregaCheckout,
  FacturacionCheckout,
  GuardarEntregaRequest,
  GuardarFacturacionRequest,
  PedidoConfirmado,
} from '../models/checkout.model';

/**
 * Pasos 1 y 2 del checkout. Operan sobre el borrador del usuario
 * (pedido_temporal), que no tiene identificador propio en la URL.
 *
 * El Paso 3 vive en PagoPedidoService: su recurso ya es un pedido
 * concreto, identificado por su número, y por eso es reanudable.
 */
@Injectable({ providedIn: 'root' })
export class CheckoutService {
  private readonly URL = `${URL_SERVICIOS}/tienda/checkout`;

  readonly entrega = signal<EntregaCheckout | null>(null);
  readonly facturacion = signal<FacturacionCheckout | null>(null);

  constructor(private http: HttpClient) {}

  // ---------- Paso 1: Entrega ----------

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

  // ---------- Paso 2: Facturación ----------

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

  /**
   * Botón [CONFIRMAR PEDIDO]. Crea el pedido y reserva el stock.
   *
   * Es el punto sin retorno del checkout: a partir de acá el borrador se
   * borra, el carrito al contado se vacía y lo que sigue (el pago) opera
   * sobre el pedido devuelto.
   *
   * Un 422 con la clave 'stock' significa que alguien se adelantó: el
   * cliente no pagó nada y vuelve al carrito.
   */
  confirmarPedido(): Observable<{ message: string; data: PedidoConfirmado }> {
    return this.http.post<{ message: string; data: PedidoConfirmado }>(`${this.URL}/confirmar`, {});
  }

  reiniciar(): void {
    this.entrega.set(null);
    this.facturacion.set(null);
  }
}