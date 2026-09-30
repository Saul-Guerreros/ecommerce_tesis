import { CommonModule } from '@angular/common';
import { Component, DestroyRef, OnDestroy, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { CarritoUiService } from '../../../core/services/carrito-ui.service';

/**
 * Panel de confirmación tras agregar un producto al carrito.
 *
 * Reemplaza al toast de ngx-toastr para este caso puntual. El texto es
 * fijo y vive en el template: NO se muestra el `message` que devuelve la
 * API. Esa respuesta sigue usándose para actualizar el estado del carrito
 * (y con él, el contador del header), pero su texto no llega a la vista.
 *
 * Los ERRORES siguen yendo por toastr: ahí el texto del backend sí es el
 * que informa el motivo real (sin stock, cantidad inválida, etc.).
 */
@Component({
  selector: 'app-confirmacion-carrito',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './confirmacion-carrito.component.html',
  styleUrl: './confirmacion-carrito.component.css',
})
export class ConfirmacionCarritoComponent implements OnDestroy {

  /** Tiempo en pantalla antes de cerrarse solo. */
  private static readonly DURACION_MS = 3000;

  visible = false;

  /**
   * setTimeout devuelve number en el navegador y Timeout en Node (SSR).
   * Se tipa como `any` para no atar el componente a ninguno de los dos.
   */
  private temporizador: any = null;

  private destroyRef = inject(DestroyRef);

  constructor(
    private carritoUi: CarritoUiService,
    private router: Router
  ) {
    this.carritoUi.confirmacion$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.mostrar());
  }

  /**
   * Si llega un segundo aviso mientras el panel sigue abierto (el usuario
   * agregó otro producto rápido), se reinicia el contador en vez de
   * encadenar dos temporizadores que cerrarían el panel antes de tiempo.
   */
  private mostrar(): void {
    this.cancelarTemporizador();
    this.visible = true;

    this.temporizador = setTimeout(
      () => this.cerrar(true),
      ConfirmacionCarritoComponent.DURACION_MS
    );
  }

  /**
   * @param abrirDrawer true cuando el cierre es por tiempo o por ✕/fondo:
   *                    ahí continúa el flujo del punto 3 y se abre el
   *                    carrito lateral. false cuando el usuario eligió
   *                    "Ir al carrito", porque ya se lo lleva a la página
   *                    completa y el drawer encima sería redundante.
   */
  cerrar(abrirDrawer: boolean): void {
    this.cancelarTemporizador();

    if (!this.visible) {
      return;
    }

    this.visible = false;

    if (abrirDrawer) {
      this.carritoUi.abrir();
    }
  }

  irAlCarrito(): void {
    this.cerrar(false);
    this.router.navigate(['/carrito-de-compra']);
  }

  private cancelarTemporizador(): void {
    if (this.temporizador !== null) {
      clearTimeout(this.temporizador);
      this.temporizador = null;
    }
  }

  /** Evita que un timer siga vivo si el componente se destruye antes. */
  ngOnDestroy(): void {
    this.cancelarTemporizador();
  }
}