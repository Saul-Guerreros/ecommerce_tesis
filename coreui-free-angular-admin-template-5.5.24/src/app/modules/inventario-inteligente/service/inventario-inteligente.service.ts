// modules/inventario-inteligente/service/inventario-inteligente.service.ts
import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { map, tap } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import {AlertaStock, ConfigInventario, FiltrosAlertas, InsightsInventario, MetricaModelo, PronosticoProducto, ResumenInventario,} from '../models/inventario-inteligente.model';


@Injectable({ providedIn: 'root' })
export class InventarioInteligenteService {
  private apiUrl = `${environment.apiUrl}/admin/inventario-inteligente`;

  /** Alertas de reposición pendientes (agotado + crítico + reponer): la usa el badge del header. */
  private readonly pendientesReposicion$ = new BehaviorSubject<number>(0);
  readonly pendientesReposicion = this.pendientesReposicion$.asObservable();

  constructor(private http: HttpClient) {}

  getResumen(): Observable<ResumenInventario> {
    return this.http.get<{ data: ResumenInventario }>(`${this.apiUrl}/resumen`).pipe(
      map((res) => res.data),
      tap((r) => this.pendientesReposicion$.next(r.alertas.agotado + r.alertas.critico + r.alertas.reponer)),
    );
  }

  getAlertas(filtros: FiltrosAlertas): Observable<AlertaStock[]> {
    let params = new HttpParams().set('estado', String(filtros.estado));
    if (filtros.tipo) params = params.set('tipo', filtros.tipo);
    if (filtros.deposito) params = params.set('deposito', String(filtros.deposito));
    if (filtros.search) params = params.set('search', filtros.search);

    return this.http.get<{ data: AlertaStock[] }>(`${this.apiUrl}/alertas`, { params }).pipe(map((res) => res.data));
  }

  atender(id: number, observacion: string | null): Observable<any> {
    return this.http.patch(`${this.apiUrl}/alertas/${id}/atender`, { observacion });
  }

  descartar(id: number, observacion: string): Observable<any> {
    return this.http.patch(`${this.apiUrl}/alertas/${id}/descartar`, { observacion });
  }

  getPronostico(productoId: number, depositoId?: number): Observable<PronosticoProducto> {
    let params = new HttpParams();
    if (depositoId) params = params.set('deposito', String(depositoId));

    return this.http
      .get<{ data: PronosticoProducto }>(`${this.apiUrl}/productos/${productoId}/pronostico`, { params })
      .pipe(map((res) => res.data));
  }

  actualizarLeadTime(productoId: number, leadTime: number | null): Observable<any> {
    return this.http.patch(`${this.apiUrl}/productos/${productoId}/lead-time`, { lead_time: leadTime });
  }

  getMetricas(): Observable<MetricaModelo[]> {
    return this.http.get<{ data: MetricaModelo[] }>(`${this.apiUrl}/metricas`).pipe(map((res) => res.data));
  }

  getConfig(): Observable<ConfigInventario> {
    return this.http.get<{ data: ConfigInventario }>(`${this.apiUrl}/config`).pipe(map((res) => res.data));
  }

  actualizarConfig(config: ConfigInventario): Observable<{ message: string; data: ConfigInventario }> {
    return this.http.put<{ message: string; data: ConfigInventario }>(`${this.apiUrl}/config`, config);
  }

  recalcular(): Observable<any> {
    return this.http.post(`${this.apiUrl}/recalcular`, {});
  }

  /** Análisis con IA. forzar = true ignora el caché y vuelve a consultar a Gemini. */
  getInsights(forzar = false): Observable<InsightsInventario> {
    return this.http
      .post<{ data: InsightsInventario }>(`${this.apiUrl}/insights`, { forzar })
      .pipe(map((res) => res.data));
  }
}
