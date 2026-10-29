import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { NavigationEnd, Router } from '@angular/router';
import { filter } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';

/**
 * Análisis de comportamiento de clientes (lado tienda).
 *
 *  - visitante(): UUID anónimo y persistente; el interceptor lo envía en
 *    el header X-Visitante. No contiene datos personales.
 *  - marcarBusqueda(): recuerda la última búsqueda devuelta por el backend.
 *  - registrarVista(): avisa que se abrió un producto. Si se llegó desde la
 *    página de resultados, la vista queda asociada a esa búsqueda.
 *
 * Nunca muestra errores: medir no puede interrumpir la compra.
 */
@Injectable({ providedIn: 'root' })
export class ActividadTiendaService {
  private static readonly CLAVE_VISITANTE = 'zi_visitante';
  private static readonly VIGENCIA_BUSQUEDA_MS = 30 * 60 * 1000;
  private static readonly RUTA_RESULTADOS = '/productos-busqueda';

  private visitanteId: string | null = null;
  private ultimaBusqueda: { id: number; en: number } | null = null;
  private urlActual = '';
  private urlAnterior = '';

  constructor(private http: HttpClient, router: Router) {
    router.events
      .pipe(filter((e): e is NavigationEnd => e instanceof NavigationEnd))
      .subscribe((e) => {
        this.urlAnterior = this.urlActual;
        this.urlActual = e.urlAfterRedirects;
      });
  }

  visitante(): string {
    if (this.visitanteId) return this.visitanteId;

    try {
      let id = localStorage.getItem(ActividadTiendaService.CLAVE_VISITANTE);
      if (!id) {
        id = this.nuevoUuid();
        localStorage.setItem(ActividadTiendaService.CLAVE_VISITANTE, id);
      }
      this.visitanteId = id;
    } catch {
      this.visitanteId = this.nuevoUuid(); // modo privado sin localStorage: vale por la sesión
    }

    return this.visitanteId;
  }

  marcarBusqueda(id: number | null | undefined): void {
    if (id) this.ultimaBusqueda = { id, en: Date.now() };
  }

  registrarVista(slug: string): void {
    this.http
      .post<void>(`${URL_SERVICIOS}/tienda/actividad/vista`, { slug, bus: this.busquedaDeOrigen() })
      .subscribe({ error: () => {} });
  }

  /** Solo si el producto se abrió desde los resultados y la búsqueda es reciente. */
  private busquedaDeOrigen(): number | null {
    const b = this.ultimaBusqueda;
    const vieneDeResultados = this.urlAnterior.startsWith(ActividadTiendaService.RUTA_RESULTADOS);

    return b && vieneDeResultados && Date.now() - b.en < ActividadTiendaService.VIGENCIA_BUSQUEDA_MS
      ? b.id
      : null;
  }

  /** crypto.randomUUID exige HTTPS o localhost; si no está, se arma un UUID v4. */
  private nuevoUuid(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
      return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = (Math.random() * 16) | 0;
      return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
  }
}