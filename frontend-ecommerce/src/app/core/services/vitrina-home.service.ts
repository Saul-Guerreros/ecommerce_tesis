import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { VitrinaHomeResponse } from '../models/vitrina.model';

/**
 * Vitrina de Home: secciones manuales (destacados, trending, más
 * vendidos) y automáticas (nuevos, en oferta, oferta del día) que arma
 * VitrinaController::home() en el backend. Endpoint público, sin auth.
 */
@Injectable({
  providedIn: 'root',
})
export class VitrinaHomeService {
  constructor(private http: HttpClient) {}

  obtener(limite: number = 12): Observable<VitrinaHomeResponse> {
    const url = `${URL_SERVICIOS}/tienda/home`;
    return this.http.get<VitrinaHomeResponse>(url, {
      params: { limite },
    });
  }
}
