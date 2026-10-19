import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Venta, VentaConfig, VentaFiltros } from '../models/venta.model';
import { environment } from '../../../../environments/environment';

/**
 * Sin create() ni update(): la factura la emite el backend cuando el
 * pedido se cobra o se entrega. Desde el panel solo se consulta y se
 * anula.
 */
@Injectable({ providedIn: 'root' })
export class VentasService {
  private apiUrl = `${environment.apiUrl}/admin/facturas-venta`;

  constructor(private http: HttpClient) {}

  getAll(filtros: VentaFiltros = {}): Observable<Venta[]> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.estado) params = params.set('estado', filtros.estado);
    if (filtros.desde)  params = params.set('desde', filtros.desde);
    if (filtros.hasta)  params = params.set('hasta', filtros.hasta);

    return this.http.get<{ data: Venta[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<VentaConfig> {
    return this.http.get<VentaConfig>(`${this.apiUrl}/config`);
  }

  getById(id: number): Observable<Venta> {
    return this.http.get<{ data: Venta }>(`${this.apiUrl}/${id}`).pipe(map((res) => res.data));
  }

  anular(id: number, motivo?: string | null): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/anular`, { motivo: motivo ?? null });
  }
}