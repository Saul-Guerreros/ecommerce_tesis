import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import {
  ConfiguracionCuota,
  GuardarCuotaPayload,
  ProductoCuota,
} from '../models/configuracion-cuota.model';

@Injectable({ providedIn: 'root' })
export class GestionCuotasService {
  private apiUrl = `${environment.apiUrl}/admin/configuraciones-cuota`;

  constructor(private http: HttpClient) {}

  getProductos(
    filtros: { search?: string; soloConCuotas?: boolean } = {}
  ): Observable<ProductoCuota[]> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.soloConCuotas) params = params.set('solo_con_cuotas', '1');

    return this.http
      .get<{ data: ProductoCuota[] }>(`${this.apiUrl}/productos`, { params })
      .pipe(map((res) => res.data));
  }

  getAll(productoId: number): Observable<ConfiguracionCuota[]> {
    const params = new HttpParams().set('producto', productoId);

    return this.http
      .get<{ data: ConfiguracionCuota[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  create(payload: GuardarCuotaPayload): Observable<any> {
    return this.http.post(this.apiUrl, payload);
  }

  update(id: number, payload: GuardarCuotaPayload): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, payload);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}