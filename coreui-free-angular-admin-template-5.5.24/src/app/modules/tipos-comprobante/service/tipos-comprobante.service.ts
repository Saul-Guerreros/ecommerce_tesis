import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { TipoComprobante } from '../models/tipo-comprobante.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class TiposComprobanteService {
  private apiUrl = `${environment.apiUrl}/admin/tipos-comprobante`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; estado?: string } = {}): Observable<TipoComprobante[]> {
    let params = new HttpParams();

    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.estado !== undefined && filtros.estado !== '') {
      params = params.set('estado', filtros.estado);
    }

    return this.http
      .get<{ data: TipoComprobante[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  /**
   * Combo para timbrado y cabecera de compra.
   * reqtim/afecstk permiten pedir solo un subconjunto: por ejemplo los
   * comprobantes que requieren timbrado, o los que suman stock.
   */
  getSelect(filtros: { reqtim?: number; afecstk?: number } = {}): Observable<TipoComprobante[]> {
    let params = new HttpParams();

    if (filtros.reqtim !== undefined) params = params.set('reqtim', String(filtros.reqtim));
    if (filtros.afecstk !== undefined) params = params.set('afecstk', String(filtros.afecstk));

    return this.http
      .get<{ data: TipoComprobante[] }>(`${this.apiUrl}/select`, { params })
      .pipe(map((res) => res.data));
  }

  create(tipo: Partial<TipoComprobante>): Observable<any> {
    return this.http.post(this.apiUrl, tipo);
  }

  update(id: number, tipo: Partial<TipoComprobante>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, tipo);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}
