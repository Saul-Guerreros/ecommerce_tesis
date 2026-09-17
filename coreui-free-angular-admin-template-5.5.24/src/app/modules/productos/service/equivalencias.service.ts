import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { ProductoEquivalencia } from '../models/producto.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class EquivalenciasService {
  private apiUrl = `${environment.apiUrl}/admin/producto-equivalencias`;

  constructor(private http: HttpClient) {}

  getByProducto(productoId: number): Observable<ProductoEquivalencia[]> {
    return this.http
      .get<{ data: ProductoEquivalencia[] }>(this.apiUrl, { params: { producto: productoId } })
      .pipe(map((res) => res.data));
  }

  create(datos: { peq_prdid: number; peq_umdid: number; peq_factor: number }): Observable<any> {
    return this.http.post(this.apiUrl, datos);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}