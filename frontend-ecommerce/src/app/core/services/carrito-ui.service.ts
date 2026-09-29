import { isPlatformBrowser } from '@angular/common';
import { Inject, Injectable, PLATFORM_ID } from '@angular/core';
import { Observable, Subject } from 'rxjs';

declare const UIkit: any;

/**
 * Control del carrito lateral (Offcanvas de UIkit).
 *
 * Responsabilidad única: abrir/cerrar el drawer. Se separa del
 * CarritoService a propósito -- ese maneja DATOS (estado, HTTP) y este
 * maneja PRESENTACIÓN. Mezclarlos ataría la lógica de negocio del carrito
 * a UIkit y rompería el testeo del service de datos.
 *
 * Es SSR-safe: en el servidor no existe `document` ni `UIkit`, así que
 * todos los métodos salen sin hacer nada.
 */
@Injectable({ providedIn: 'root' })
export class CarritoUiService {

  /** Debe coincidir con el id del <div uk-offcanvas> del componente. */
  private readonly SELECTOR = '#cart-offcanvas';

  private readonly esNavegador: boolean;

  /**
   * Canal de aviso para el panel de confirmación.
   *
   * Subject y no BehaviorSubject a propósito: esto es un EVENTO ("se
   * agregó algo"), no un estado. Con BehaviorSubject, cualquier componente
   * que se suscribiera después recibiría el último aviso y volvería a
   * mostrar el panel sin motivo.
   */
  private readonly confirmacion = new Subject<void>();

  readonly confirmacion$: Observable<void> = this.confirmacion.asObservable();
  constructor(@Inject(PLATFORM_ID) platformId: Object) {
    this.esNavegador = isPlatformBrowser(platformId);
  }

  /**
   * Dispara el panel "¡Listo! Producto agregado al carrito".
   *
   * No recibe texto por parámetro: el mensaje es fijo del frontend. El
   * `message` que devuelve CarritoController se usa solo para el estado,
   * nunca se pinta en pantalla.
   */
  confirmarAgregado(): void {
    if (!this.esNavegador) {
      return;
    }
    this.confirmacion.next();
  }

  abrir(): void {
    this.ejecutar('show');
  }

  cerrar(): void {
    this.ejecutar('hide');
  }

  /**
   * UIkit.offcanvas(el) devuelve la instancia YA creada por el atributo
   * uk-offcanvas del template; no crea una segunda. El reintento corto
   * cubre el caso de que se llame antes de que UIkit termine de
   * inicializar el nodo (por ejemplo, un clic muy temprano tras la
   * hidratación).
   */
  private ejecutar(accion: 'show' | 'hide', intentos = 10): void {
    if (!this.esNavegador) {
      return;
    }

    if (typeof UIkit === 'undefined' || !document.querySelector(this.SELECTOR)) {
      if (intentos > 0) {
        setTimeout(() => this.ejecutar(accion, intentos - 1), 60);
      }
      return;
    }

    const instancia = UIkit.offcanvas(document.querySelector(this.SELECTOR));
    instancia?.[accion]();
  }
}