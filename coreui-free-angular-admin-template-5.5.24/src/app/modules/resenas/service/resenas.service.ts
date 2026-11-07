import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ListadoResenas, ResenaFiltros } from '../models/resena.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ResenasService {
  private apiUrl = `${environment.apiUrl}/admin/resenas`;

  constructor(private http: HttpClient) {}

  getAll(filtros: ResenaFiltros = {}): Observable<ListadoResenas> {
    let params = new HttpParams();
    Object.entries(filtros).forEach(([clave, valor]) => {
      if (valor !== undefined && valor !== null && valor !== '') params = params.set(clave, String(valor));
    });
    return this.http.get<ListadoResenas>(this.apiUrl, { params });
  }

  /** Visible → oculta (exige motivo) / oculta → visible. */
  cambiarEstado(id: number, motivo: string | null = null): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, { res_motocu: motivo });
  }

  /** Texto vacío = quitar la respuesta. */
  responder(id: number, respuesta: string | null): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}/respuesta`, { res_respue: respuesta });
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}