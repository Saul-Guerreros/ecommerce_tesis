import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import {
  ConfiguracionTienda,
  ActualizarConfiguracionTiendaPayload,
} from '../models/configuracion-tienda.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ConfiguracionTiendaService {
  private apiUrl = `${environment.apiUrl}/admin/configuracion-tienda`;

  constructor(private http: HttpClient) {}

  getConfig(): Observable<ConfiguracionTienda> {
    return this.http.get<{ data: ConfiguracionTienda }>(this.apiUrl).pipe(map((res) => res.data));
  }

  update(payload: ActualizarConfiguracionTiendaPayload): Observable<any> {
    return this.http.patch(this.apiUrl, payload);
  }
}