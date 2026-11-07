import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { DatosContacto } from '../models/datos-contacto.model';
import { environment } from '../../../../environments/environment';

interface RespuestaContacto {
  message?: string;
  data: DatosContacto;
}

@Injectable({ providedIn: 'root' })
export class DatosContactoService {
  private apiUrl = `${environment.apiUrl}/admin/datos-contacto`;

  constructor(private http: HttpClient) {}

  get(): Observable<DatosContacto> {
    return this.http.get<RespuestaContacto>(this.apiUrl).pipe(map((res) => res.data));
  }

  update(payload: DatosContacto): Observable<RespuestaContacto> {
    return this.http.put<RespuestaContacto>(this.apiUrl, payload);
  }
}