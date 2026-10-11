import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

import { PedidoFiltros, PedidosConfig, PedidosPaginados } from '../models/pedido.model';
import {
  ConfirmarPagoPayload, MotivoRechazo, PedidoDetalle, RechazarPagoPayload,
} from '../models/pedido-detalle.model';
import { environment } from '../../../../environments/environment';

/**
 * Sin headers manuales: el token JWT lo agrega auth.interceptor.ts,
 * registrado globalmente en app.config.ts.
 */
@Injectable({ providedIn: 'root' })
export class PedidosService {
  private apiUrl = `${environment.apiUrl}/admin/pedidos`;

  constructor(private http: HttpClient) {}

  // ---------- Bandeja ----------

  list(filtros: PedidoFiltros): Observable<PedidosPaginados> {
    return this.http.get<PedidosPaginados>(this.apiUrl, {
      params: this.construirParams(filtros),
    });
  }

  config(): Observable<PedidosConfig> {
    return this.http.get<PedidosConfig>(`${this.apiUrl}/config`);
  }

  exportar(filtros: PedidoFiltros): Observable<Blob> {
    const { page, per_page, ...sinPaginacion } = filtros;

    return this.http.get(`${this.apiUrl}/exportar`, {
      params: this.construirParams(sinPaginacion),
      responseType: 'blob',
    });
  }

  // ---------- Gestión del pedido ----------

  show(pedidoId: number): Observable<{ data: PedidoDetalle }> {
    return this.http.get<{ data: PedidoDetalle }>(`${this.apiUrl}/${pedidoId}`);
  }

  /** Catálogo de motivos para el modal de rechazo. */
  configGestion(): Observable<{ motivos_rechazo: MotivoRechazo[] }> {
    return this.http.get<{ motivos_rechazo: MotivoRechazo[] }>(`${this.apiUrl}/gestion/config`);
  }

  confirmarPago(
    pedidoId: number,
    pagoId: number,
    payload: ConfirmarPagoPayload,
  ): Observable<{ message: string; data: PedidoDetalle }> {
    return this.http.post<{ message: string; data: PedidoDetalle }>(
      `${this.apiUrl}/${pedidoId}/pagos/${pagoId}/confirmar`,
      payload,
    );
  }

  rechazarPago(
    pedidoId: number,
    pagoId: number,
    payload: RechazarPagoPayload,
  ): Observable<{ message: string; data: PedidoDetalle }> {
    return this.http.post<{ message: string; data: PedidoDetalle }>(
      `${this.apiUrl}/${pedidoId}/pagos/${pagoId}/rechazar`,
      payload,
    );
  }

  cambiarEstado(
    pedidoId: number,
    estado: string,
    comentario?: string | null,
  ): Observable<{ message: string; data: PedidoDetalle }> {
    return this.http.patch<{ message: string; data: PedidoDetalle }>(
      `${this.apiUrl}/${pedidoId}/estado`,
      { estado, comentario: comentario ?? null },
    );
  }

  // ---------- Auxiliar ----------

  private construirParams(filtros: PedidoFiltros): HttpParams {
    let params = new HttpParams();

    Object.entries(filtros).forEach(([clave, valor]) => {
      if (valor === null || valor === undefined) return;

      const texto = String(valor).trim();
      if (texto === '') return;

      params = params.set(clave, texto);
    });

    return params;
  }

  /** El comprobante se trae como blob porque la ruta exige token JWT. */
    comprobante(pedidoId: number, pagoId: number): Observable<Blob> {
    return this.http.get(
        `${this.apiUrl}/${pedidoId}/pagos/${pagoId}/comprobante`,
        { responseType: 'blob' },
    );
    }

}