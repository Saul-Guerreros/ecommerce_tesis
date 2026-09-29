import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { URL_SERVICIOS } from '../../config/config';
import { CatalogoDirecciones, Direccion } from '../models/direccion.model';

@Injectable({ providedIn: 'root' })
export class DireccionClienteService {
  private readonly URL = `${URL_SERVICIOS}/tienda/perfil/direcciones`;

  constructor(private http: HttpClient) {}

  catalogos() {
    return this.http.get<CatalogoDirecciones>(`${this.URL}/catalogos`);
  }

  listar() {
    return this.http.get<{ data: Direccion[] }>(this.URL);
  }

  crear(datos: Partial<Direccion>) {
    return this.http.post<{ message: string; data: Direccion }>(this.URL, datos);
  }

  actualizar(id: number, datos: Partial<Direccion>) {
    return this.http.put<{ message: string; data: Direccion }>(`${this.URL}/${id}`, datos);
  }

  eliminar(id: number) {
    return this.http.delete<{ message: string }>(`${this.URL}/${id}`);
  }

  marcarPredeterminada(id: number) {
    return this.http.patch<{ message: string; data: Direccion }>(`${this.URL}/${id}/predeterminada`, {});
  }
}