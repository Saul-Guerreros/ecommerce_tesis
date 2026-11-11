import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { ChequeDeclarado, RegistrarPagoRequest } from '../models/checkout.model';
import { ConfigPagoServicioResponse, PagoServicioCliente } from '../models/servicio-tecnico.model';

/** Respuesta común de registrar / reemplazar: estado agregado + pagos actualizados. */
export interface RespuestaPagoServicio {
  message: string;
  data: { estado_pago: string | null; pagos: PagoServicioCliente[] };
}

/** Anticipo: transferencia/billetera con comprobante, o cheque con sus datos. */
export interface AnticipoServicioRequest {
  mpa_codigo: 'TRANSFERENCIA' | 'BILLETERA_MOVIL' | 'CHEQUE';
  monto: number;
  comprobante: File | null;
  cheque: ChequeDeclarado | null;
}

/**
 * Pago del servicio técnico desde la tienda (C4). Espejo de PagoPedidoService:
 * mismo payload que el Paso 3 del checkout (cheque incluido).
 */
@Injectable({ providedIn: 'root' })
export class PagoServicioService {
  private readonly URL = `${URL_SERVICIOS}/tienda/servicios`;

  constructor(private http: HttpClient) {}

  config(numero: string): Observable<ConfigPagoServicioResponse> {
    return this.http.get<ConfigPagoServicioResponse>(`${this.URL}/${encodeURIComponent(numero)}/pago`);
  }

  registrar(numero: string, payload: RegistrarPagoRequest): Observable<RespuestaPagoServicio> {
    const formData = new FormData();
    formData.append('modo', payload.modo);

    if (payload.modo === 'unico') {
      formData.append('mpa_codigo', payload.mpa_codigo!);
      if (payload.comprobante) formData.append('comprobante', payload.comprobante);
      if (payload.cheque) this.adjuntarCheque(formData, 'cheque', payload.cheque);
    } else {
      (payload.pagos ?? []).forEach((pago, i) => {
        formData.append(`pagos[${i}][mpa_codigo]`, pago.mpa_codigo);
        formData.append(`pagos[${i}][monto]`, String(pago.monto));
        if (pago.comprobante) formData.append(`pagos[${i}][comprobante]`, pago.comprobante);
        if (pago.cheque) this.adjuntarCheque(formData, `pagos[${i}][cheque]`, pago.cheque);
      });
    }

    return this.http.post<RespuestaPagoServicio>(`${this.URL}/${encodeURIComponent(numero)}/pago`, formData);
  }

  registrarAnticipo(numero: string, datos: AnticipoServicioRequest): Observable<RespuestaPagoServicio> {
    const formData = new FormData();
    formData.append('mpa_codigo', datos.mpa_codigo);
    formData.append('monto', String(datos.monto));
    if (datos.comprobante) formData.append('comprobante', datos.comprobante);
    if (datos.cheque) this.adjuntarCheque(formData, 'cheque', datos.cheque);

    return this.http.post<RespuestaPagoServicio>(`${this.URL}/${encodeURIComponent(numero)}/anticipo`, formData);
  }

  reemplazarComprobante(numero: string, spaId: number, archivo: File): Observable<RespuestaPagoServicio> {
    const formData = new FormData();
    formData.append('comprobante', archivo);

    return this.http.post<RespuestaPagoServicio>(
      `${this.URL}/${encodeURIComponent(numero)}/pagos/${spaId}/comprobante`, formData);
  }

  descargarArchivo(url: string): Observable<Blob> {
    return this.http.get(url, { responseType: 'blob' });
  }

  /** Igual que PagoPedidoService: la fecha de cobro solo viaja en el diferido. */
  private adjuntarCheque(formData: FormData, prefijo: string, cheque: ChequeDeclarado): void {
    formData.append(`${prefijo}[tipo]`, cheque.tipo ?? '');
    formData.append(`${prefijo}[banco]`, cheque.banco.trim());
    formData.append(`${prefijo}[nrodoc]`, cheque.nrodoc.trim());
    formData.append(`${prefijo}[nombre]`, cheque.nombre.trim());
    formData.append(`${prefijo}[nro]`, cheque.nro.trim());

    if (cheque.tipo === 'DIFERIDO' && cheque.fecha_cobro) {
      formData.append(`${prefijo}[fecha_cobro]`, cheque.fecha_cobro);
    }
  }
}