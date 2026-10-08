import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Producto, ProductoConfig, ProductoSelect, Paginado } from '../models/producto.model';
import { environment } from '../../../../environments/environment';


@Injectable({ providedIn: 'root' })
export class ProductosService {
  private apiUrl = `${environment.apiUrl}/admin/productos`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; marca?: number | null; estado?: string } = {},page = 1,perPage = 10): Observable<Paginado<Producto>> {
  let params = new HttpParams()
    .set('page', String(page))
    .set('per_page', String(perPage));

  if (filtros.search) params = params.set('search', filtros.search);
  if (filtros.marca)  params = params.set('marca', String(filtros.marca));
  if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);

  // Ya no se hace map(res => res.data): se necesita también "meta".
  return this.http.get<Paginado<Producto>>(this.apiUrl, { params });
  }

  // GET /productos/{id} — trae el producto con su modelo base (padre) y
  // sus variantes propias, si las tiene (el listado no los incluye).
  getOne(id: number): Observable<Producto> {
    return this.http.get<{ data: Producto }>(`${this.apiUrl}/${id}`).pipe(map((res) => res.data));
  }

  getConfig(): Observable<ProductoConfig> {
    return this.http.get<ProductoConfig>(`${this.apiUrl}/config`);
  }

  // GET /productos/select — lista liviana para el buscador de lineas de compra.
  select(): Observable<ProductoSelect[]> {
    return this.http.get<{ data: ProductoSelect[] }>(`${this.apiUrl}/select`).pipe(map((res) => res.data));
  }

  create(producto: Partial<Producto>): Observable<any> {
    return this.http.post(this.apiUrl, producto);
  }

  update(id: number, producto: Partial<Producto>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, producto);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}