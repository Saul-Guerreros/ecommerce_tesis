// src/app/modules/productos/service/equivalencias.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { ProductoEquivalencia } from '../models/producto.model';
import { environment } from '../../../../environments/environment';

export interface EquivalenciaPayload {
  peq_prdid: number;
  peq_umdid: number;
  peq_factor: number;
}

@Injectable({ providedIn: 'root' })
export class EquivalenciasService {
  private apiUrl = `${environment.apiUrl}/admin/producto-equivalencias`;

  constructor(private http: HttpClient) {}

  getByProducto(productoId: number): Observable<ProductoEquivalencia[]> {
    return this.http
      .get<{ data: ProductoEquivalencia[] }>(this.apiUrl, { params: { producto: productoId } })
      .pipe(map((res) => res.data));
  }

  create(datos: EquivalenciaPayload): Observable<any> {
    return this.http.post(this.apiUrl, datos);
  }

  update(id: number, datos: EquivalenciaPayload): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, datos);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}