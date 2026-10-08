import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import { CampoFicha } from '../models/ficha-tecnica.model';

@Injectable({ providedIn: 'root' })
export class FichaTecnicaService {
  private apiUrl = `${environment.apiUrl}/admin/productos`;

  constructor(private http: HttpClient) {}

  /** El token lo agrega el AuthInterceptor; acá solo se fuerza la respuesta JSON. */
  private headers(): HttpHeaders {
    return new HttpHeaders({ Accept: 'application/json' });
  }

  /** Campos de la plantilla de la subcategoría + valores ya guardados del producto. */
  getCampos(productoId: number, categoriaId: number): Observable<CampoFicha[]> {
    const params = new HttpParams().set('categoria', categoriaId);

    return this.http
      .get<{ data: CampoFicha[] }>(`${this.apiUrl}/${productoId}/ficha-tecnica`, {
        params,
        headers: this.headers(),
      })
      .pipe(map((res) => res.data));
  }
}
