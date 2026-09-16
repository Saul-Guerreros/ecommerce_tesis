import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { ConfiguracionPrecio, ActualizarConfiguracionPrecioPayload } from '../models/configuracion-precio.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ConfiguracionPrecioService {
  private apiUrl = `${environment.apiUrl}/admin/configuracion-precio`;

  constructor(private http: HttpClient) {}

  getConfig(): Observable<ConfiguracionPrecio> {
    return this.http.get<{ data: ConfiguracionPrecio }>(this.apiUrl).pipe(map((res) => res.data));
  }

  update(payload: ActualizarConfiguracionPrecioPayload): Observable<any> {
    return this.http.patch(this.apiUrl, payload);
  }
}