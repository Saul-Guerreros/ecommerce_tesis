import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { AjusteInventario, AjusteConfig } from '../models/ajuste-inventario.model';
import { ProductoBusqueda } from '../../facturas-compra/models/compra.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class AjustesInventarioService {
  private apiUrl = `${environment.apiUrl}/admin/ajustes-inventario`;
  private productosUrl = `${environment.apiUrl}/admin/productos`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; deposito?: number | null; estado?: string } = {}): Observable<AjusteInventario[]> {
    let params = new HttpParams();
    if (filtros.search)   params = params.set('search', filtros.search);
    if (filtros.deposito) params = params.set('deposito', String(filtros.deposito));
    if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);

    return this.http.get<{ data: AjusteInventario[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<AjusteConfig> {
    return this.http.get<AjusteConfig>(`${this.apiUrl}/config`);
  }

  getById(id: number): Observable<AjusteInventario> {
    return this.http.get<{ data: AjusteInventario }>(`${this.apiUrl}/${id}`).pipe(map((res) => res.data));
  }

  create(ajuste: Partial<AjusteInventario>): Observable<any> {
    return this.http.post(this.apiUrl, ajuste);
  }

  confirmar(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/confirmar`, {});
  }

  anular(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/anular`, {});
  }

  eliminarBorrador(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  // Reutiliza el mismo endpoint liviano de productos que usa Factura de Compra.
  buscarProductos(): Observable<ProductoBusqueda[]> {
    return this.http.get<{ data: ProductoBusqueda[] }>(`${this.productosUrl}/select`).pipe(map((res) => res.data));
  }
}