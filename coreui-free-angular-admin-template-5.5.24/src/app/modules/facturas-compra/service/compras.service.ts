import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Compra, CompraConfig, ProductoBusqueda } from '../models/compra.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ComprasService {
  private apiUrl = `${environment.apiUrl}/admin/compras`;
  private productosUrl = `${environment.apiUrl}/admin/productos`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; proveedor?: number | null; estado?: string } = {}): Observable<Compra[]> {
    let params = new HttpParams();
    if (filtros.search)   params = params.set('search', filtros.search);
    if (filtros.proveedor) params = params.set('proveedor', String(filtros.proveedor));
    if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);

    return this.http.get<{ data: Compra[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<CompraConfig> {
    return this.http.get<CompraConfig>(`${this.apiUrl}/config`);
  }

  getById(id: number): Observable<Compra> {
    return this.http.get<{ data: Compra }>(`${this.apiUrl}/${id}`).pipe(map((res) => res.data));
  }

  create(compra: Partial<Compra>): Observable<any> {
    return this.http.post(this.apiUrl, compra);
  }

  anular(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/anular`, {});
  }

  /**
   * Busqueda de productos para el detalle (reemplaza el F3 del sistema de
   * referencia). Reutiliza /admin/productos/select (activos, liviano) y se
   * filtra en el cliente por codigo o nombre.
   */
  buscarProductos(): Observable<ProductoBusqueda[]> {
    return this.http.get<{ data: ProductoBusqueda[] }>(`${this.productosUrl}/select`).pipe(map((res) => res.data));
  }
}