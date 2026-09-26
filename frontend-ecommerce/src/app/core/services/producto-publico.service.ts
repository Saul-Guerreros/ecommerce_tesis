import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_BACKEND, URL_SERVICIOS } from '../../config/config';
import { ProductosPaginados } from '../models/producto.model';

/**
 * Catálogo público: solo trae productos con publicacion_web.pub_estado =
 * PUBLICADO (filtro estado_publicacion=publicado, resuelto en el backend
 * por ProductoController::index). Así, cuando desde el dashboard se pasa
 * un producto a "Publicado", automáticamente empieza a aparecer acá.
 */
@Injectable({
  providedIn: 'root',
})
export class ProductoPublicoService {
  constructor(private http: HttpClient) {}

  listarPublicados(perPage: number = 8): Observable<ProductosPaginados> {
    const url = `${URL_SERVICIOS}/productos`;
    return this.http.get<ProductosPaginados>(url, {
      params: {
        estado_publicacion: 'publicado',
        per_page: perPage,
      },
    });
  }

  /**
   * Arma la URL completa de la imagen principal de la ficha web.
   * `imagen_principal` viene como ruta relativa dentro del disco "public"
   * de Laravel (p.ej. "publicaciones/3/portada.webp").
   */
  imagenUrl(rutaRelativa: string | null | undefined): string | null {
    if (!rutaRelativa) {
      return null;
    }
    return `${URL_BACKEND}storage/${rutaRelativa}`;
  }
}
