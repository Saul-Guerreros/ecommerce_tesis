import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import {
  ConfiguracionEnvio,
  ActualizarConfiguracionEnvioPayload,
} from '../models/configuracion-envio.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ConfiguracionEnvioService {
  private apiUrl = `${environment.apiUrl}/admin/configuracion-envio`;

  constructor(private http: HttpClient) {}

  getConfig(): Observable<ConfiguracionEnvio> {
    return this.http.get<{ data: ConfiguracionEnvio }>(this.apiUrl).pipe(map((res) => res.data));
  }

  update(payload: ActualizarConfiguracionEnvioPayload): Observable<any> {
    return this.http.patch(this.apiUrl, payload);
  }
}