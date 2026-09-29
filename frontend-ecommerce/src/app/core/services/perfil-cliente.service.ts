import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { URL_SERVICIOS } from '../../config/config';
import { PerfilCliente } from '../models/perfil-cliente.model';

@Injectable({ providedIn: 'root' })
export class PerfilClienteService {
  private readonly URL = `${URL_SERVICIOS}/tienda/perfil`;

  constructor(private http: HttpClient) {}

  obtener() {
    return this.http.get<{ data: PerfilCliente }>(this.URL);
  }

  // POST (no PUT): el backend necesita multipart/form-data para el avatar,
  // y PHP no lo parsea de forma confiable en verbos PUT/PATCH nativos
  // (mismo criterio que ya usa el proyecto para las imágenes de sliders).
  actualizar(formData: FormData) {
    return this.http.post<{ message: string; data: PerfilCliente }>(this.URL, formData);
  }

  actualizarPassword(datos: {
    password_actual: string;
    password_nuevo: string;
    password_nuevo_confirmation: string;
  }) {
    return this.http.patch<{ message: string }>(`${this.URL}/password`, datos);
  }
}