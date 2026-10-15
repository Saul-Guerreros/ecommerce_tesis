import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Cargo } from '../models/cargo.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class CargosService {
  private apiUrl = `${environment.apiUrl}/admin/cargos`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; estado?: string } = {}): Observable<Cargo[]> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);

    return this.http.get<{ data: Cargo[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  create(cargo: Partial<Cargo>): Observable<any> {
    return this.http.post(this.apiUrl, cargo);
  }

  update(id: number, cargo: Partial<Cargo>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, cargo);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}