import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { StockSucursalesResponse } from '../models/stock-sucursal.model';

/**
 * Disponibilidad por sucursal de un producto: consume
 * VitrinaController::stockSucursales().
 *
 * Endpoint separado del detalle a propósito. El stock cambia mientras el
 * cliente navega, así que se consulta al abrir el modal y no cuando se
 * carga la ficha. Público, sin auth, igual que el resto de /api/tienda/*.
 */
@Injectable({
  providedIn: 'root',
})
export class StockSucursalService {
  constructor(private http: HttpClient) {}

  obtener(slug: string): Observable<StockSucursalesResponse> {
    const url = `${URL_SERVICIOS}/tienda/producto/${encodeURIComponent(slug)}/stock-sucursales`;
    return this.http.get<StockSucursalesResponse>(url);
  }
}