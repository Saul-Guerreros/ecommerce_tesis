import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import {
  FiltroPedidos, PedidoDetalle, PedidoPendienteItem, PedidosPaginados,
} from '../models/pedido-cliente.model';

/**
 * "Mis Pedidos": lado cliente.
 *
 * Servicio aparte de CheckoutService y PagoPedidoService: estos dos
 * operan sobre UN pedido en curso (el borrador o el que se está pagando);
 * éste consulta el historial completo del cliente. Mezclar las tres cosas
 * en un solo servicio haría que un componente de sólo lectura (el
 * listado) dependiera de la lógica de escritura del checkout.
 */
@Injectable({ providedIn: 'root' })
export class PedidoClienteService {
  private readonly URL = `${URL_SERVICIOS}/tienda/pedidos`;

  constructor(private http: HttpClient) {}

  listar(filtro: FiltroPedidos = 'todos', page = 1): Observable<PedidosPaginados> {
    return this.http.get<PedidosPaginados>(this.URL, { params: { filtro, page } });
  }

  detalle(numero: string): Observable<{ data: PedidoDetalle }> {
    return this.http.get<{ data: PedidoDetalle }>(`${this.URL}/${numero}`);
  }

  /**
   * Para el punto rojo sobre el ícono de perfil, la tarjeta flotante de la
   * Home y el contador de "Mis pedidos" en el dropdown. Las tres piezas
   * comparten esta misma consulta. Array vacío cuando no hay ninguno: no
   * es un error, el componente simplemente no muestra nada.
   */
  pendientes(): Observable<{ data: PedidoPendienteItem[]; total: number }> {
    return this.http.get<{ data: PedidoPendienteItem[]; total: number }>(`${this.URL}/pendientes`);
  }

  /**
   * Sube un comprobante nuevo para un pago que el admin rechazó.
   *
   * FormData y no JSON porque viaja un archivo. No se manda método ni
   * monto: los fijó el pago original y el backend los ignora — acá sólo
   * se corrige el comprobante.
   */
  reemplazarComprobante(
    numero: string,
    pagoId: number,
    archivo: File,
  ): Observable<{ message: string }> {
    const datos = new FormData();
    datos.append('comprobante', archivo);

    return this.http.post<{ message: string }>(
      `${this.URL}/${numero}/pagos/${pagoId}/comprobante`,
      datos,
    );
  }

  /**
   * Descarga un archivo protegido (el comprobante de pago) PASANDO por
   * HttpClient, no por window.open(url) directo.
   *
   * El endpoint exige sesión (auth:api) y Angular adjunta el token sólo
   * en los pedidos que hace a través de HttpClient — un window.open(url)
   * navega directo a esa URL sin ese token, y el backend lo rechaza. Acá
   * se pide el archivo como Blob (con el token puesto) y recién ese Blob
   * se abre en una pestaña nueva.
   */
  descargarArchivo(url: string): Observable<Blob> {
    return this.http.get(url, { responseType: 'blob' });
  }
}