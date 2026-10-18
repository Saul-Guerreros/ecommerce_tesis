import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import {
  ConfigPagoPedidoResponse,
  RegistrarPagoRequest,
  ResultadoPago,
} from '../models/checkout.model';

/**
 * Paso 3 del checkout: pago de un pedido que ya existe.
 *
 * Servicio aparte de CheckoutService a propósito. Los pasos 1 y 2 operan
 * sobre el borrador del usuario, que no tiene identificador propio; acá el
 * recurso es un pedido concreto, identificado por su número en la URL.
 * Esa diferencia es la que hace que esta pantalla sea reanudable: el
 * cliente puede cerrar el navegador y volver al link más tarde.
 */
@Injectable({ providedIn: 'root' })
export class PagoPedidoService {
  private readonly URL = `${URL_SERVICIOS}/tienda/pedidos`;

  constructor(private http: HttpClient) {}

  /**
   * Datos de la pantalla de pago. Devuelve 422 si el pedido ya fue pagado
   * o si venció: el componente usa esa respuesta para redirigir en vez de
   * mostrar un formulario inútil.
   */
  config(numero: string): Observable<ConfigPagoPedidoResponse> {
    return this.http.get<ConfigPagoPedidoResponse>(`${this.URL}/${numero}/pago`);
  }

  /**
   * Un solo endpoint para los dos modos. Va como FormData porque lleva
   * archivos; las claves indexadas pagos[i][campo] son las que Laravel
   * entiende como array de objetos con archivo adentro.
   *
   * A diferencia del flujo anterior ya no existe "conservar": el pago se
   * declara una sola vez, así que el archivo siempre viaja completo.
   */
  registrar(numero: string, payload: RegistrarPagoRequest) {
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
      });
    }

    return this.http.post<{ message: string; data: ResultadoPago }>(
      `${this.URL}/${numero}/pago`,
      formData,
    );
  }

  /**
   * Pantalla final. Se resuelve por número de pedido, así que sigue siendo
   * válida de forma permanente: recargar o volver con el botón del
   * navegador no rompe nada.
   */
  resultado(numero: string): Observable<{ data: ResultadoPago }> {
    return this.http.get<{ data: ResultadoPago }>(`${this.URL}/${numero}/resultado`);
  }
}