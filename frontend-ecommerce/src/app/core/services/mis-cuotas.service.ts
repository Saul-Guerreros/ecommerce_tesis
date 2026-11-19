import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { ChequeDeclarado } from '../models/checkout.model';
import { CompraConCuotas, MisCuotasRespuesta } from '../models/cuota-cliente.model';
import {
  ConfigPagoCuotasResponse, RegistrarPagoCuotasRequest, ResultadoPagoCuota,
} from '../models/cuota-pago.model';

@Injectable({ providedIn: 'root' })
export class MisCuotasService {
  private base = `${URL_SERVICIOS}/tienda`;

  constructor(private http: HttpClient) {}

  /** Listado de la sección: compras a crédito con cuotas generadas. */
  listar(): Observable<{ data: CompraConCuotas[] }> {
    return this.http.get<{ data: CompraConCuotas[] }>(`${this.base}/cuotas`);
  }

  getCuotas(numeroPedido: string): Observable<MisCuotasRespuesta> {
    return this.http.get<MisCuotasRespuesta>(`${this.base}/cuotas/pedido/${numeroPedido}`);
  }

  /**
   * Datos de la pantalla de pago. Devuelve 422 si las cuotas ya no son
   * pagables: el componente usa esa respuesta para volver al detalle en
   * vez de mostrar un formulario inútil.
   */
  configPago(numeroPedido: string, cuotas: number[]): Observable<ConfigPagoCuotasResponse> {
    return this.http.get<ConfigPagoCuotasResponse>(
      `${this.base}/cuotas/pedido/${numeroPedido}/pago`,
      { params: { cuotas: cuotas.join(',') } },
    );
  }

  /**
   * Un solo endpoint para los dos modos. Va como FormData porque lleva
   * archivos; las claves indexadas pagos[i][campo] son las que Laravel
   * entiende como array de objetos con archivo adentro.
   */
  pagar(cuotas: number[], payload: RegistrarPagoCuotasRequest) {
    const fd = new FormData();

    cuotas.forEach((id) => fd.append('cuotas[]', String(id)));
    fd.append('modo', payload.modo);

    if (payload.modo === 'unico') {
      fd.append('mpa_codigo', payload.mpa_codigo!);
      if (payload.comprobante) fd.append('comprobante', payload.comprobante);
      if (payload.cheque) this.adjuntarCheque(fd, 'cheque', payload.cheque);
    } else {
      (payload.pagos ?? []).forEach((p, i) => {
        fd.append(`pagos[${i}][mpa_codigo]`, p.mpa_codigo);
        fd.append(`pagos[${i}][monto]`, String(p.monto));
        if (p.comprobante) fd.append(`pagos[${i}][comprobante]`, p.comprobante);
        if (p.cheque) this.adjuntarCheque(fd, `pagos[${i}][cheque]`, p.cheque);
      });
    }

    return this.http.post<{ message: string; data: ResultadoPagoCuota }>(
      `${this.base}/cuotas/pagar`,
      fd,
    );
  }

  /** Pantalla final. Se resuelve por el id de la declaración. */
  resultadoPago(cpaId: number): Observable<{ data: ResultadoPagoCuota }> {
    return this.http.get<{ data: ResultadoPagoCuota }>(
      `${this.base}/cuotas/pagos/${cpaId}/resultado`,
    );
  }

  reemplazarComprobante(cpmId: number, archivo: File): Observable<any> {
    const fd = new FormData();
    fd.append('comprobante', archivo);

    return this.http.post(`${this.base}/cuotas/medios/${cpmId}/comprobante`, fd);
  }

  urlComprobante(cpmId: number): string {
    return `${this.base}/cuotas/medios/${cpmId}/comprobante`;
  }

  urlFactura(numeroPedido: string): string {
    return `${this.base}/cuotas/pedido/${numeroPedido}/factura`;
  }

  private adjuntarCheque(fd: FormData, prefijo: string, cheque: ChequeDeclarado): void {
    fd.append(`${prefijo}[tipo]`, cheque.tipo ?? '');
    fd.append(`${prefijo}[banco]`, cheque.banco.trim());
    fd.append(`${prefijo}[nrodoc]`, cheque.nrodoc.trim());
    fd.append(`${prefijo}[nombre]`, cheque.nombre.trim());
    fd.append(`${prefijo}[nro]`, cheque.nro.trim());

    // Sólo el diferido tiene fecha de cobro.
    if (cheque.tipo === 'DIFERIDO' && cheque.fecha_cobro) {
      fd.append(`${prefijo}[fecha_cobro]`, cheque.fecha_cobro);
    }
  }
}