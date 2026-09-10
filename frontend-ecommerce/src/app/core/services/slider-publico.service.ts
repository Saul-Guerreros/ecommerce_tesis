import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { SlidersHomeResponse } from '../models/slider.model';

/**
 * Banners del home (hero + promo), administrados desde el Panel Admin
 * (módulo Sliders). Endpoint público, sin auth — GET /api/tienda/sliders.
 */
@Injectable({
  providedIn: 'root',
})
export class SliderPublicoService {
  constructor(private http: HttpClient) {}

  obtener(): Observable<SlidersHomeResponse> {
    const url = `${URL_SERVICIOS}/tienda/sliders`;
    return this.http.get<SlidersHomeResponse>(url);
  }
}