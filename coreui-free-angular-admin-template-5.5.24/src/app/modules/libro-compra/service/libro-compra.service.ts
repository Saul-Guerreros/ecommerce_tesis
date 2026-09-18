import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { LibroCompra, LibroCompraTotales, ProveedorOption } from '../models/libro-compra.model';
import { environment } from '../../../../environments/environment';

export interface LibroCompraFiltros {
  desde?: string;
  hasta?: string;
  proveedor?: number | null;
  tipo?: string;
  estado?: string;
  search?: string;
}

@Injectable({ providedIn: 'root' })
export class LibroCompraService {
  private apiUrl = `${environment.apiUrl}/admin/libro-compra`;

  constructor(private http: HttpClient) {}

  getAll(filtros: LibroCompraFiltros = {}): Observable<{ data: LibroCompra[]; totales: LibroCompraTotales }> {
    let params = new HttpParams();
    if (filtros.desde)     params = params.set('desde', filtros.desde);
    if (filtros.hasta)     params = params.set('hasta', filtros.hasta);
    if (filtros.proveedor) params = params.set('proveedor', String(filtros.proveedor));
    if (filtros.tipo)      params = params.set('tipo', filtros.tipo);
    if (filtros.estado !== undefined) params = params.set('estado', filtros.estado);
    if (filtros.search)    params = params.set('search', filtros.search);

    return this.http.get<{ data: LibroCompra[]; totales: LibroCompraTotales }>(this.apiUrl, { params });
  }

  getConfig(): Observable<{ proveedores: ProveedorOption[] }> {
    return this.http.get<{ proveedores: ProveedorOption[] }>(`${this.apiUrl}/config`);
  }
}