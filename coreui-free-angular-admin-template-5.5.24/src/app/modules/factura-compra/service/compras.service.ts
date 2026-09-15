import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { CompraCab, CompraConfig } from '../models/compra.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ComprasService {
  private apiUrl = `${environment.apiUrl}/admin/compras`;

  constructor(private http: HttpClient) {}

  // No hay filtro de "activo/inactivo": una compra es Confirmada o
  // Anulada (com_estfac), no se desactiva.
  getAll(filtros: { search?: string; proveedor?: number | null; estado?: string } = {}): Observable<CompraCab[]> {
    let params = new HttpParams();
    if (filtros.search)    params = params.set('search', filtros.search);
    if (filtros.proveedor) params = params.set('proveedor', String(filtros.proveedor));
    if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);

    return this.http.get<{ data: CompraCab[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<CompraConfig> {
    return this.http.get<CompraConfig>(`${this.apiUrl}/config`);
  }

  get(id: number): Observable<CompraCab> {
    return this.http.get<{ data: CompraCab }>(`${this.apiUrl}/${id}`).pipe(map((res) => res.data));
  }

  // No existe update(): una compra confirmada no se edita. Si esta mal
  // cargada, se anula (anular()) y se carga una nueva.
  create(compra: Partial<CompraCab>): Observable<any> {
    return this.http.post(this.apiUrl, compra);
  }

  anular(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/anular`, {});
  }
}