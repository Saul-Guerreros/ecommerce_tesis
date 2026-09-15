import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Precio } from '../models/precio.model';
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

  // Unico campo que se manda: prd_margen. El backend recalcula prd_prevta.
  update(id: number, margen: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}`, { prd_margen: margen });
  }
}