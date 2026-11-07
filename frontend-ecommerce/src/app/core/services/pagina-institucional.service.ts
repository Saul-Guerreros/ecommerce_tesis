import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { catchError, map, shareReplay } from 'rxjs/operators';
import { URL_SERVICIOS } from '../../config/config';
import { DatosContacto, PaginaNosotros, RedSocial } from '../models/pagina-institucional.model';

@Injectable({ providedIn: 'root' })
export class PaginaInstitucionalService {
  /** Contacto se usa en el footer de TODAS las páginas: se pide una sola vez. */
  private contacto$?: Observable<DatosContacto | null>;

  constructor(private http: HttpClient) {}

  obtenerNosotros(): Observable<PaginaNosotros> {
    return this.http
      .get<{ data: PaginaNosotros }>(`${URL_SERVICIOS}/tienda/nosotros`)
      .pipe(map((res) => res.data));
  }

  /** null = página no publicada o error (la vista muestra su estado vacío). */
  obtenerContacto(): Observable<DatosContacto | null> {
    return (this.contacto$ ??= this.http
      .get<{ data: DatosContacto }>(`${URL_SERVICIOS}/tienda/contacto`)
      .pipe(
        map((res) => res.data),
        catchError(() => {
          this.contacto$ = undefined; // permite reintentar en la próxima navegación
          return of(null);
        }),
        shareReplay(1),
      ));
  }

  // ---------- Helpers de enlaces (armados solo con datos validados) ----------

  enlaceWhatsapp(c: DatosContacto): string | null {
    const numero = (c.dco_whatsapp ?? '').replace(/\D/g, '');
    if (!numero) return null;
    const texto = c.dco_whatsapp_mensaje ? `?text=${encodeURIComponent(c.dco_whatsapp_mensaje)}` : '';
    return `https://wa.me/${numero}${texto}`;
  }

  enlaceTelefono(c: DatosContacto): string | null {
    const numero = (c.dco_telefono ?? '').replace(/[^\d+]/g, '');
    return numero ? `tel:${numero}` : null;
  }

  enlaceEmail(c: DatosContacto): string | null {
    return c.dco_email ? `mailto:${c.dco_email}` : null;
  }

  enlaceComoLlegar(c: DatosContacto): string | null {
    if (!this.coordenadasValidas(c)) return null;
    return `https://www.google.com/maps/dir/?api=1&destination=${c.dco_mapa_lat},${c.dco_mapa_lng}`;
  }

  coordenadasValidas(c: DatosContacto): boolean {
    const lat = Number(c.dco_mapa_lat);
    const lng = Number(c.dco_mapa_lng);
    return c.dco_mapa_lat !== null && c.dco_mapa_lng !== null
      && Number.isFinite(lat) && Number.isFinite(lng)
      && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
  }

  redesSociales(c: DatosContacto): RedSocial[] {
    const redes: RedSocial[] = [
      { nombre: 'Facebook', url: c.dco_facebook ?? '', icono: 'facebook' },
      { nombre: 'Instagram', url: c.dco_instagram ?? '', icono: 'instagram' },
      { nombre: 'YouTube', url: c.dco_youtube ?? '', icono: 'youtube' },
      { nombre: 'TikTok', url: c.dco_tiktok ?? '', icono: null },
    ];
    // Segunda barrera: solo https (el backend ya valida el dominio).
    return redes.filter((r) => r.url.startsWith('https://'));
  }
}