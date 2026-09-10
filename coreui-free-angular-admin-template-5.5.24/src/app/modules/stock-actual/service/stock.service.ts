import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { StockItem, StockConfig } from '../models/stock.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class StockService {
  private apiUrl = `${environment.apiUrl}/admin/stock`;

  constructor(private http: HttpClient) {}

  getAll(filtros: {
    producto?: number | null; deposito?: number | null;
    marca?: number | null; bajoMinimo?: boolean;
  } = {}): Observable<StockItem[]> {
    let params = new HttpParams();
    if (filtros.producto) params = params.set('producto', String(filtros.producto));
    if (filtros.deposito) params = params.set('deposito', String(filtros.deposito));
    if (filtros.marca)    params = params.set('marca', String(filtros.marca));
    if (filtros.bajoMinimo) params = params.set('bajo_minimo', '1');

    return this.http.get<{ data: StockItem[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<StockConfig> {
    return this.http.get<StockConfig>(`${this.apiUrl}/config`);
  }
}