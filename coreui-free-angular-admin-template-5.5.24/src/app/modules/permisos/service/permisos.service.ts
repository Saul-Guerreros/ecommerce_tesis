import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { CambioRol, MatrizPermisos } from '../models/permisos.model';

@Injectable({ providedIn: 'root' })
export class PermisosService {
  private readonly apiUrl = `${environment.apiUrl}/admin/permisos/matriz`;

  constructor(private http: HttpClient) {}

  matriz(): Observable<MatrizPermisos> {
    return this.http.get<MatrizPermisos>(this.apiUrl);
  }

  guardar(cambios: CambioRol[]): Observable<{ message: string; roles_actualizados: number }> {
    return this.http.put<{ message: string; roles_actualizados: number }>(this.apiUrl, { cambios });
  }
}
