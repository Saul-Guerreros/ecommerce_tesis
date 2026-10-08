import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { TarifaEnvio, SucursalOption, CiudadOption } from '../models/tarifa-envio.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class TarifaEnvioService {
  private apiUrl = `${environment.apiUrl}/admin/tarifas-envio`;

  constructor(private http: HttpClient) {}

  getAll(): Observable<TarifaEnvio[]> {
    return this.http.get<{ data: TarifaEnvio[] }>(this.apiUrl).pipe(map((res) => res.data));
  }

  getSucursales(): Observable<SucursalOption[]> {
    return this.http
      .get<{ data: SucursalOption[] }>(`${environment.apiUrl}/admin/sucursales/select`)
      .pipe(map((res) => res.data));
  }

  getCiudades(): Observable<CiudadOption[]> {
    return this.http
      .get<{ data: CiudadOption[] }>(`${environment.apiUrl}/admin/ciudades/select`)
      .pipe(map((res) => res.data));
  }

  create(tarifa: Partial<TarifaEnvio>): Observable<any> {
    return this.http.post(this.apiUrl, tarifa);
  }

  update(id: number, tarifa: Partial<TarifaEnvio>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, tarifa);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}