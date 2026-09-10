import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import { Categoria, Marca, TipoIva, UnidadMedida } from '../models/producto.model';

@Injectable({
  providedIn: 'root',
})
export class CatalogoService {
   constructor(private http: HttpClient) {}

  categorias(): Observable<Categoria[]> {
    return this.http
      .get<{ data: Categoria[] }>(`${environment.apiUrl}/categorias`)
      .pipe(map((res) => res.data));
  }

  marcas(): Observable<Marca[]> {
    return this.http
      .get<{ data: Marca[] }>(`${environment.apiUrl}/marcas`)
      .pipe(map((res) => res.data));
  }

  tiposIva(): Observable<TipoIva[]> {
    return this.http
      .get<{ data: TipoIva[] }>(`${environment.apiUrl}/tipos-iva`)
      .pipe(map((res) => res.data));
  }

  unidadesMedida(): Observable<UnidadMedida[]> {
    return this.http
      .get<{ data: UnidadMedida[] }>(`${environment.apiUrl}/unidades-medida`)
      .pipe(map((res) => res.data));
  }

}
