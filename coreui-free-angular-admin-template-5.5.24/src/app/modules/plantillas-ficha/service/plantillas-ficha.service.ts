import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import {
  CategoriaAtributo,
  CategoriaAtributoConfig,
  SubcategoriaPlantilla,
} from '../models/categoria-atributo.model';

@Injectable({ providedIn: 'root' })
export class PlantillasFichaService {
  private apiUrl = `${environment.apiUrl}/admin/categoria-atributos`;

  constructor(private http: HttpClient) {}

  /** El token lo agrega el AuthInterceptor; acá solo se fuerza la respuesta JSON. */
  private headers(): HttpHeaders {
    return new HttpHeaders({ Accept: 'application/json' });
  }

  getSubcategorias(): Observable<SubcategoriaPlantilla[]> {
    return this.http
      .get<{ data: SubcategoriaPlantilla[] }>(`${this.apiUrl}/categorias`, { headers: this.headers() })
      .pipe(map((res) => res.data));
  }

  getConfig(): Observable<CategoriaAtributoConfig> {
    return this.http.get<CategoriaAtributoConfig>(`${this.apiUrl}/config`, { headers: this.headers() });
  }

  getPlantilla(categoriaId: number): Observable<CategoriaAtributo[]> {
    const params = new HttpParams().set('categoria', categoriaId);

    return this.http
      .get<{ data: CategoriaAtributo[] }>(this.apiUrl, { params, headers: this.headers() })
      .pipe(map((res) => res.data));
  }

  create(item: Partial<CategoriaAtributo>): Observable<any> {
    return this.http.post(this.apiUrl, item, { headers: this.headers() });
  }

  update(id: number, item: Partial<CategoriaAtributo>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, item, { headers: this.headers() });
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`, { headers: this.headers() });
  }
}
