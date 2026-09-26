import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_BACKEND, URL_SERVICIOS } from '../../config/config';
import { VitrinaHome } from '../models/vitrina.model';

@Injectable({
  providedIn: 'root',
})
export class VitrinaPublicaService {
  constructor(private http: HttpClient) {}

  home(): Observable<{ data: VitrinaHome }> {
    return this.http.get<{ data: VitrinaHome }>(`${URL_SERVICIOS}/tienda/home`);
  }

  /** Arma la URL completa de la imagen (ya viene resuelta con storage/ desde el backend, esto es por si cambia). */
  imagenUrl(ruta: string | null): string | null {
    if (!ruta) {
      return null;
    }
    return ruta.startsWith('http') ? ruta : `${URL_BACKEND}${ruta}`;
  }
}
