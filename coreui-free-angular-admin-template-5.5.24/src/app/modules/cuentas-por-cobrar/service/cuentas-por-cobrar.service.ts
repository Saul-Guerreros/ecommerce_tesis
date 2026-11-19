import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import {
  ClienteOption, CuentaPorCobrar, DetalleCuentaPorCobrar,
  MetodoPagoOption, NuevoCobroCuota,
} from '../models/cuenta-por-cobrar.model';
import { environment } from '../../../../environments/environment';

export interface FiltrosCartera {
  search?: string;
  cliente?: number | null;
  estado?: string;
  desde?: string;
  hasta?: string;
}

@Injectable({ providedIn: 'root' })
export class CuentasPorCobrarService {
  private apiUrl = `${environment.apiUrl}/admin/cuentas-por-cobrar`;

  constructor(private http: HttpClient) {}

  getAll(filtros: FiltrosCartera = {}): Observable<{
    data: CuentaPorCobrar[];
    totales: { facturas: number; saldo: number; vencidas: number };
  }> {
    let params = new HttpParams();

    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.cliente) params = params.set('cliente', String(filtros.cliente));
    if (filtros.estado) params = params.set('estado', filtros.estado);
    if (filtros.desde) params = params.set('desde', filtros.desde);
    if (filtros.hasta) params = params.set('hasta', filtros.hasta);

    return this.http.get<{
      data: CuentaPorCobrar[];
      totales: { facturas: number; saldo: number; vencidas: number };
    }>(this.apiUrl, { params });
  }

  getConfig(): Observable<{ clientes: ClienteOption[]; metodos: MetodoPagoOption[] }> {
    return this.http.get<{ clientes: ClienteOption[]; metodos: MetodoPagoOption[] }>(
      `${this.apiUrl}/config`,
    );
  }

  /** Cuotas de una factura, con su cobro activo si lo tiene. */
  getDetalle(venId: number): Observable<DetalleCuentaPorCobrar> {
    return this.http.get<DetalleCuentaPorCobrar>(`${this.apiUrl}/${venId}`);
  }

  /** Las cuotas van en el cuerpo: un recibo puede cubrir varias. */
  registrarCobro(cobro: NuevoCobroCuota): Observable<any> {
    return this.http.post(`${this.apiUrl}/cobrar`, cobro);
  }

  /** Anula el recibo entero: todas sus cuotas vuelven a Pendiente. */
  anularRecibo(recId: number, motivo: string): Observable<any> {
    return this.http.patch(`${this.apiUrl}/recibos/${recId}/anular`, { rec_motanu: motivo });
  }
}