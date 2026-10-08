import { HttpClient } from '@angular/common/http';
import { Injectable, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import {
  ConfigEntregaResponse,
  ConfigFacturacionResponse,
  ConfigPagoResponse,
  EntregaCheckout,
  FacturacionCheckout,
  GuardarEntregaRequest,
  GuardarFacturacionRequest,
  MetodoPagoCodigo,
  PagoCheckout,
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

  guardarPago(mpaCodigo: MetodoPagoCodigo, comprobante: File | null) {
    const formData = new FormData();
    formData.append('mpa_codigo', mpaCodigo);
    if (comprobante) formData.append('comprobante', comprobante);

    return this.http.post<{ message: string; data: PagoCheckout }>(`${this.URL}/pago`, formData);
  }

  reiniciar(): void {
    this.entrega.set(null);
    this.facturacion.set(null);
  }
}