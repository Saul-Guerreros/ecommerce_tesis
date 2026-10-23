import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { RegistrarPagoRequest } from '../models/checkout.model';
import { ConfigPagoServicioResponse, PagoServicioCliente } from '../models/servicio-tecnico.model';

/** Respuesta común de registrar / reemplazar: estado agregado + pagos actualizados. */
export interface RespuestaPagoServicio {
  message: string;
  data: { estado_pago: string | null; pagos: PagoServicioCliente[] };
}

/**
 * Pago del servicio técnico desde la tienda (C4). Espejo de PagoPedidoService:
 * mismo payload que el Paso 3 del checkout, así la pantalla reutiliza
 * app-pago-dividido sin adaptar nada.
 */
@Injectable({ providedIn: 'root' })
export class PagoServicioService {
  private readonly URL = `${URL_SERVICIOS}/tienda/servicios`;

  constructor(private http: HttpClient) {}

  config(numero: string): Observable<ConfigPagoServicioResponse> {
    return this.http.get<ConfigPagoServicioResponse>(`${this.URL}/${encodeURIComponent(numero)}/pago`);
  }

  /**
   * FormData porque lleva archivos. No se envía "conservar" (lo emite
   * app-pago-dividido para el checkout): acá no hay borrador.
   */
  registrar(numero: string, payload: RegistrarPagoRequest): Observable<RespuestaPagoServicio> {
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

    return this.http.post<RespuestaPagoServicio>(`${this.URL}/${encodeURIComponent(numero)}/pago`, formData);
  }

  reemplazarComprobante(numero: string, spaId: number, archivo: File): Observable<RespuestaPagoServicio> {
    const formData = new FormData();
    formData.append('comprobante', archivo);

    return this.http.post<RespuestaPagoServicio>(
      `${this.URL}/${encodeURIComponent(numero)}/pagos/${spaId}/comprobante`, formData);
  }

  /** El comprobante exige el token: se pide como Blob por HttpClient (igual que en pedidos). */
  descargarArchivo(url: string): Observable<Blob> {
    return this.http.get(url, { responseType: 'blob' });
  }
}
