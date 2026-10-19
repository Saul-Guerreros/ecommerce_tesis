import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { URL_SERVICIOS } from '../../config/config';
import { ConfigServicio, ServicioCliente } from '../models/servicio-tecnico.model';

@Injectable({ providedIn: 'root' })
export class ServicioTecnicoService {
  private readonly URL = `${URL_SERVICIOS}/tienda/servicios`;

  constructor(private http: HttpClient) {}

  config() {
    return this.http.get<ConfigServicio>(`${this.URL}/config`);
  }

  costoRetiro(dirId: number) {
    return this.http.get<{ costo: number }>(`${this.URL}/costo-retiro/${dirId}`);
  }

  /** multipart/form-data por las fotos. */
  crear(datos: FormData) {
    return this.http.post<{ message: string; data: { sse_numero: string } }>(this.URL, datos);
  }

  listar() {
    return this.http.get<{ data: ServicioCliente[] }>(this.URL);
  }

  detalle(numero: string) {
    return this.http.get<{ data: ServicioCliente }>(`${this.URL}/${encodeURIComponent(numero)}`);
  }

  aprobarPresupuesto(numero: string) {
    return this.http.post<{ message: string; data: ServicioCliente }>(
      `${this.URL}/${encodeURIComponent(numero)}/presupuesto/aprobar`, {});
  }

  rechazarPresupuesto(numero: string, motivo: string) {
    return this.http.post<{ message: string; data: ServicioCliente }>(
      `${this.URL}/${encodeURIComponent(numero)}/presupuesto/rechazar`, { motivo });
  }

  /** PDF con el JWT del cliente (por eso Blob y no un enlace directo). */
  constancia(numero: string, tipo: 'recepcion' | 'retiro') {
    return this.http.get(`${this.URL}/${encodeURIComponent(numero)}/constancia/${tipo}`, { responseType: 'blob' });
  }
}
