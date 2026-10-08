import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { ConfiguracionPago } from '../models/configuracion-pago.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ConfiguracionPagoService {
  private apiUrl = `${environment.apiUrl}/admin/configuracion-tienda`;

  constructor(private http: HttpClient) {}

  getConfig(): Observable<ConfiguracionPago> {
    return this.http.get<{ data: ConfiguracionPago }>(this.apiUrl).pipe(map((res) => res.data));
  }

  update(payload: ConfiguracionPago): Observable<any> {
    return this.http.patch(this.apiUrl, payload);
  }
}