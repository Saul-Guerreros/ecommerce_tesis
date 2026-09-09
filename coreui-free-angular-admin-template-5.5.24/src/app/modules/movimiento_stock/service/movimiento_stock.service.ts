import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { MovimientoStock, KardexConfig } from '../models/movimiento-stock.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class KardexService {
  private apiUrl = `${environment.apiUrl}/admin/movimientos-stock`;

  constructor(private http: HttpClient) {}

  getAll(filtros: {
    producto?: number | null; deposito?: number | null;
    tipo?: string; desde?: string; hasta?: string;
  } = {}): Observable<MovimientoStock[]> {
    let params = new HttpParams();
    if (filtros.producto) params = params.set('producto', String(filtros.producto));
    if (filtros.deposito) params = params.set('deposito', String(filtros.deposito));
    if (filtros.tipo)     params = params.set('tipo', filtros.tipo);
    if (filtros.desde)    params = params.set('desde', filtros.desde);
    if (filtros.hasta)    params = params.set('hasta', filtros.hasta);

    return this.http.get<{ data: MovimientoStock[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<KardexConfig> {
    return this.http.get<KardexConfig>(`${this.apiUrl}/config`);
  }
}