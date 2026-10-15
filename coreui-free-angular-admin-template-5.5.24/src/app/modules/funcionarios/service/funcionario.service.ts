import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../../config/config';
import {
  BusquedaPersona, Funcionario, FuncionarioConfig, FuncionarioPayload, ListaFuncionarios,
} from '../models/funcionario.model';

/**
 * Servicio del módulo Funcionarios (admin).
 * Los headers Authorization y Accept los agrega authInterceptor (app.config.ts).
 */
@Injectable({ providedIn: 'root' })
export class FuncionarioService {
  private readonly base = `${URL_SERVICIOS}/admin/funcionarios`;

  constructor(private http: HttpClient) {}

  config(): Observable<FuncionarioConfig> {
    return this.http.get<FuncionarioConfig>(`${this.base}/config`);
  }

  buscarPersona(tipoDocumento: number, documento: string): Observable<BusquedaPersona> {
    return this.http.get<BusquedaPersona>(`${this.base}/buscar-persona`, {
      params: { tipo_documento: tipoDocumento, documento },
    });
  }

  list(page: number = 1, search: string = '', estado: string = '', cargoId: string = ''): Observable<ListaFuncionarios> {
    return this.http.get<ListaFuncionarios>(this.base, {
      params: { page, search, estado, cargo_id: cargoId },
    });
  }

  store(data: FuncionarioPayload): Observable<{ data: Funcionario; message?: string }> {
    return this.http.post<{ data: Funcionario; message?: string }>(this.base, data);
  }

  update(id: number, data: FuncionarioPayload): Observable<{ data: Funcionario; message?: string }> {
    return this.http.put<{ data: Funcionario; message?: string }>(`${this.base}/${id}`, data);
  }

  cambiarEstado(id: number): Observable<any> {
    return this.http.patch(`${this.base}/${id}/estado`, {});
  }

  destroy(id: number): Observable<any> {
    return this.http.delete(`${this.base}/${id}`);
  }
}
