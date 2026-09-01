import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { TipoIva } from '../models/tipo-iva.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class TiposIvaService {
  private apiUrl = `${environment.apiUrl}/admin/tipos-iva`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { estado?: string } = {}): Observable<TipoIva[]> {
    let params = new HttpParams();

    if (filtros.estado !== undefined && filtros.estado !== '') {
      params = params.set('estado', filtros.estado);
    }

    return this.http
      .get<{ data: TipoIva[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  // Combo para producto y lineas de compra (fases 4 y 5).
  getSelect(): Observable<TipoIva[]> {
    return this.http
      .get<{ data: TipoIva[] }>(`${this.apiUrl}/select`)
      .pipe(map((res) => res.data));
  }

  create(tipo: Partial<TipoIva>): Observable<any> {
    return this.http.post(this.apiUrl, tipo);
  }

  update(id: number, tipo: Partial<TipoIva>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, tipo);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}
