import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { FacturacionServicioOpciones, GuardarFacturacionServicio } from '../models/factura-servicio.model';

/**
 * Facturación del servicio técnico desde la tienda: datos fiscales (CI/RUC)
 * antes de pagar y descarga de la factura (KuDE) cuando la DNIT la aprueba.
 */
@Injectable({ providedIn: 'root' })
export class FacturaServicioService {
  private readonly URL = `${URL_SERVICIOS}/tienda/servicios`;

  constructor(private http: HttpClient) {}

  opciones(numero: string): Observable<{ data: FacturacionServicioOpciones }> {
    return this.http.get<{ data: FacturacionServicioOpciones }>(`${this.URL}/${encodeURIComponent(numero)}/facturacion`);
  }

  guardar(numero: string, datos: GuardarFacturacionServicio): Observable<{ message: string; data: FacturacionServicioOpciones }> {
    return this.http.put<{ message: string; data: FacturacionServicioOpciones }>(
      `${this.URL}/${encodeURIComponent(numero)}/facturacion`, datos);
  }

  /** El KuDE exige el token: se pide como Blob (igual que la constancia). */
  kude(numero: string): Observable<Blob> {
    return this.http.get(`${this.URL}/${encodeURIComponent(numero)}/factura/kude`, { responseType: 'blob' });
  }
}
