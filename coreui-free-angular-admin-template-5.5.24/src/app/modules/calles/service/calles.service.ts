import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Calle, CalleConfig } from '../models/calle.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class CallesService {
  private apiUrl = `${environment.apiUrl}/admin/calles`;

  constructor(private http: HttpClient) {}

  getAll(
    filtros: { search?: string; ciudad?: number | null; barrio?: number | null; estado?: string } = {}
  ): Observable<Calle[]> {
    let params = new HttpParams();

    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.ciudad) params = params.set('ciudad', String(filtros.ciudad));
    if (filtros.barrio) params = params.set('barrio', String(filtros.barrio));
    if (filtros.estado !== undefined && filtros.estado !== '') {
      params = params.set('estado', filtros.estado);
    }

    return this.http
      .get<{ data: Calle[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  /**
   * Trae ciudades y barrios en una sola llamada. El formulario filtra los
   * barrios por ciudad en el cliente, sin un segundo viaje al servidor.
   */
  getConfig(): Observable<CalleConfig> {
    return this.http.get<CalleConfig>(`${this.apiUrl}/config`);
  }

  create(calle: Partial<Calle>): Observable<any> {
    return this.http.post(this.apiUrl, calle);
  }

  update(id: number, calle: Partial<Calle>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, calle);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}
