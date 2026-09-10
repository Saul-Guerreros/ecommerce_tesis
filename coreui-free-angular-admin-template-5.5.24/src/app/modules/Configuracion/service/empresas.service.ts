import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Empresa } from '../models/empresa.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class EmpresasService {
  private apiUrl = `${environment.apiUrl}/admin/empresas`;

  constructor(private http: HttpClient) {}

  getAll(): Observable<Empresa[]> {
    return this.http
      .get<{ data: Empresa[] }>(this.apiUrl)
      .pipe(map((res) => res.data));
  }

  getById(id: number): Observable<Empresa> {
    return this.http
      .get<{ data: Empresa }>(`${this.apiUrl}/${id}`)
      .pipe(map((res) => res.data));
  }

  create(empresa: Partial<Empresa>): Observable<any> {
    return this.http.post(this.apiUrl, empresa);
  }

  update(id: number, empresa: Partial<Empresa>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, empresa);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}
