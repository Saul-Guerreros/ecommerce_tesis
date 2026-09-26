import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { ProductoDetalleResponse } from '../models/producto-detalle.model';

/**
 * Detalle público de un producto por slug: consume VitrinaController::detalle(),
 * el mismo endpoint que arma "producto", "descripcion", "galeria",
 * "especificaciones", "colores_disponibles" y "relacionados" en una sola
 * respuesta. Público, sin auth (igual que el resto de /api/tienda/*).
 */
@Injectable({
  providedIn: 'root',
})
export class ProductoDetalleService {
  constructor(private http: HttpClient) {}

  obtener(slug: string): Observable<ProductoDetalleResponse> {
    const url = `${URL_SERVICIOS}/tienda/producto/${encodeURIComponent(slug)}`;
    return this.http.get<ProductoDetalleResponse>(url);
  }
}
