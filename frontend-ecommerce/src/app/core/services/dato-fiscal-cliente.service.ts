import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { URL_SERVICIOS } from '../../config/config';
import { DatoFiscal } from '../models/dato-fiscal.model';

@Injectable({ providedIn: 'root' })
export class DatoFiscalClienteService {
  private readonly URL = `${URL_SERVICIOS}/tienda/perfil/dato-fiscal`;

  constructor(private http: HttpClient) {}

  listar() {
    return this.http.get<{ data: DatoFiscal[] }>(this.URL);
  }

  crear(datos: Partial<DatoFiscal>) {
    return this.http.post<{ message: string; data: DatoFiscal }>(this.URL, datos);
  }

  actualizar(id: number, datos: Partial<DatoFiscal>) {
    return this.http.put<{ message: string; data: DatoFiscal }>(`${this.URL}/${id}`, datos);
  }

  eliminar(id: number) {
    return this.http.delete<{ message: string }>(`${this.URL}/${id}`);
  }

  /**
   * Pregunta al backend si "numero" es el RUC propio del cliente
   * logueado (contra cliente.cli_ruc, o calculado desde su cedula si
   * no lo tiene guardado). Devuelve con que telefono/correo
   * autocompletar cuando corresponde. Ver DatoFiscalClienteController::
   * verificarPropio para el detalle de la logica.
   */
  verificarPropio(numero: string) {
    return this.http.get<RucPropioResponse>(`${this.URL}/verificar-propio`, {
      params: { numero },
    });
  }
}

export interface RucPropioResponse {
  es_propio: boolean;
  fuente?: 'perfil' | 'calculado';
  telefono?: string | null;
  correo?: string;
  ofrecer_guardar_ruc?: boolean;
}