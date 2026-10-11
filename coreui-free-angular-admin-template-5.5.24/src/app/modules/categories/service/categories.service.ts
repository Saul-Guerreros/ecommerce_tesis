import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Categoria } from '../models/categoria.model';
import { environment } from '../../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class CategoriesService {
  private apiUrl = `${environment.apiUrl}/categorias`;

  constructor(private http: HttpClient) {}

  getAll(): Observable<Categoria[]> {
    return this.http.get<{ data: Categoria[] }>(this.apiUrl).pipe(
      map(res => res.data)
    );
  }

  getById(id: number): Observable<Categoria> {
    return this.http.get<Categoria>(`${this.apiUrl}/${id}`);
  }

  create(categoria: Partial<Categoria>): Observable<Categoria> {
    return this.http.post<Categoria>(this.apiUrl, categoria);
  }

  update(id: number, categoria: Partial<Categoria>): Observable<Categoria> {
    return this.http.put<Categoria>(`${this.apiUrl}/${id}`, categoria);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
  subirImagen(id: number, archivo: File): Observable<Categoria> {
    const datos = new FormData();
    datos.append('imagen', archivo);
    return this.http.post<Categoria>(`${this.apiUrl}/${id}/imagen`, datos);
  }

  eliminarImagen(id: number): Observable<Categoria> {
    return this.http.delete<Categoria>(`${this.apiUrl}/${id}/imagen`);
  }
}