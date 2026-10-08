import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { FaqTienda, ListadoFaq, TemaFaq } from '../models/faq-tienda.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class FaqTiendaService {
  private apiUrl = `${environment.apiUrl}/admin/faq-tienda`;

  constructor(private http: HttpClient) {}

  temas(): Observable<TemaFaq[]> {
    return this.http.get<{ data: { temas: TemaFaq[] } }>(`${this.apiUrl}/config`).pipe(map((res) => res.data.temas));
  }

  getAll(filtros: { search?: string; tema?: string; estado?: string } = {}): Observable<ListadoFaq> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.tema) params = params.set('tema', filtros.tema);
    if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);

    return this.http
      .get<{ data: FaqTienda[]; meta: { caracteres_activos: number } }>(this.apiUrl, { params })
      .pipe(map((res) => ({ faqs: res.data, caracteresActivos: res.meta.caracteres_activos })));
  }

  create(faq: Partial<FaqTienda>): Observable<any> { return this.http.post(this.apiUrl, faq); }
  update(id: number, faq: Partial<FaqTienda>): Observable<any> { return this.http.put(`${this.apiUrl}/${id}`, faq); }
  delete(id: number): Observable<any> { return this.http.delete(`${this.apiUrl}/${id}`); }
  toggleEstado(id: number): Observable<any> { return this.http.patch(`${this.apiUrl}/${id}/estado`, {}); }
}