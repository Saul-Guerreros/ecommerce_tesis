import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { CuentaPorPagar, CuotaPendiente, NuevoPagoCompra, ProveedorOption } from '../models/cuenta-por-pagar.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class CuentasPorPagarService {
  private apiUrl     = `${environment.apiUrl}/admin/cuentas-por-pagar`;
  private comprasUrl = `${environment.apiUrl}/admin/compras`;
  private pagosUrl   = `${environment.apiUrl}/admin/pagos-compra`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; proveedor?: number | null; estado?: string } = {}): Observable<CuentaPorPagar[]> {
    let params = new HttpParams();
    if (filtros.search)    params = params.set('search', filtros.search);
    if (filtros.proveedor) params = params.set('proveedor', String(filtros.proveedor));
    if (filtros.estado !== undefined) params = params.set('estado', filtros.estado);

    return this.http.get<{ data: CuentaPorPagar[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<{ proveedores: ProveedorOption[] }> {
    return this.http.get<{ proveedores: ProveedorOption[] }>(`${this.apiUrl}/config`);
  }

  /** Cuotas de una factura puntual, con su pago activo si tiene. */
  getCuotas(comId: number): Observable<CuotaPendiente[]> {
    return this.http.get<{ data: CuotaPendiente[] }>(`${this.comprasUrl}/${comId}/cuotas`)
      .pipe(map((res) => res.data));
  }

  registrarPago(pago: NuevoPagoCompra): Observable<any> {
    return this.http.post(this.pagosUrl, pago);
  }

  anularPago(pagoId: number, motivo: string): Observable<any> {
  return this.http.patch(`${this.pagosUrl}/${pagoId}/anular`, { pgo_motanu: motivo });
}
}