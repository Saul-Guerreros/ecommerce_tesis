import { isPlatformBrowser } from '@angular/common';
import { Inject, Injectable, PLATFORM_ID } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { SolicitudCredito } from '../models/solicitud-compra.model';

const CLAVE = 'solicitud_credito_pendiente';

/**
 * Guarda la solicitud de crédito preparada en el Detalle del Producto para
 * que el flujo posterior (datos personales, laborales y referenciales)
 * pueda retomarla. Todavía NO se envía al backend.
 *
 * Vive en memoria y se replica en sessionStorage para sobrevivir a una
 * recarga o a un cambio de página dentro de la misma pestaña. Es SSR-safe.
 */
@Injectable({ providedIn: 'root' })
export class SolicitudCreditoService {
  private readonly esNavegador: boolean;
  private readonly pendiente: BehaviorSubject<SolicitudCredito | null>;

  readonly pendiente$: Observable<SolicitudCredito | null>;

  constructor(@Inject(PLATFORM_ID) platformId: Object) {
    this.esNavegador = isPlatformBrowser(platformId);
    this.pendiente = new BehaviorSubject<SolicitudCredito | null>(this.leer());
    this.pendiente$ = this.pendiente.asObservable();
  }

  preparar(solicitud: SolicitudCredito): void {
    this.pendiente.next(solicitud);
    this.escribir(solicitud);
  }

  obtener(): SolicitudCredito | null {
    return this.pendiente.getValue();
  }

  limpiar(): void {
    this.pendiente.next(null);
    this.escribir(null);
  }

  private leer(): SolicitudCredito | null {
    if (!this.esNavegador) return null;

    try {
      const crudo = sessionStorage.getItem(CLAVE);
      return crudo ? (JSON.parse(crudo) as SolicitudCredito) : null;
    } catch {
      return null;
    }
  }

  private escribir(solicitud: SolicitudCredito | null): void {
    if (!this.esNavegador) return;

    try {
      if (solicitud) {
        sessionStorage.setItem(CLAVE, JSON.stringify(solicitud));
      } else {
        sessionStorage.removeItem(CLAVE);
      }
    } catch {
      /* almacenamiento bloqueado o lleno: el estado en memoria alcanza */
    }
  }
}