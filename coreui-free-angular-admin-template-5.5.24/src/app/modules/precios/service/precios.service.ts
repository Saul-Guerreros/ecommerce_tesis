import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Precio, ActualizarPrecioPayload } from '../../precios/models/precio.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class PreciosService {
  private apiUrl = `${environment.apiUrl}/admin/precios`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string } = {}): Observable<Precio[]> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);

    return this.http.get<{ data: Precio[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  // Los 3 campos SIEMPRE se mandan (null = "usar el general"). Antes
  // solo se mandaba prd_margen; ahora el backend espera los 3 juntos.
  update(id: number, payload: ActualizarPrecioPayload): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}`, payload);
  }
}