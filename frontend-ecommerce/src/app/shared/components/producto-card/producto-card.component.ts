import { CommonModule } from '@angular/common';
import { Component, Input, inject } from '@angular/core';
import { RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { URL_BACKEND } from '../../../config/config';
import { ProductoVitrina, SeleccionCompra } from '../../../core/models/vitrina.model';
import { CarritoService } from '../../../core/services/carrito.service';
import { CarritoUiService } from '../../../core/services/carrito-ui.service';
import { MonedaPyPipe } from '../../pipes/moneda-py.pipe';
import { CuoteroComponent } from '../cuotero/cuotero.component';

/**
 * Tarjeta de producto con cuotero y botón de carrito.
 * Encapsula la lógica de "agregar al carrito" para reutilizarla
 * en cualquier listado (búsqueda, home, relacionados...).
 */
@Component({
  selector: 'app-producto-card',
  standalone: true,
  imports: [CommonModule, RouterModule, MonedaPyPipe, CuoteroComponent],
  templateUrl: './producto-card.component.html',
  styleUrl: './producto-card.component.css',
})
export class ProductoCardComponent {
  @Input({ required: true }) producto!: ProductoVitrina;

  private readonly carritoService = inject(CarritoService);
  private readonly carritoUi = inject(CarritoUiService);
  private readonly toastr = inject(ToastrService);

  /** Petición en curso SOLO de esta tarjeta (no bloquea a las demás). */
  agregando = false;

  private readonly imagenFallback =
    'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22300%22%20height%3D%22225%22%20viewBox%3D%220%200%20300%20225%22%3E%3Crect%20width%3D%22300%22%20height%3D%22225%22%20fill%3D%22%23e9e9e9%22/%3E%3Ctext%20x%3D%22150%22%20y%3D%22117%22%20font-family%3D%22Arial%2C%20sans-serif%22%20font-size%3D%2216%22%20fill%3D%22%23999%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3ESin%20imagen%3C/text%3E%3C/svg%3E';

  get imagen(): string {
    const img = this.producto.imagen;
    if (!img) return this.imagenFallback;
    if (img.startsWith('http')) return img;
    return `${URL_BACKEND.replace(/\/$/, '')}/${img.replace(/^\//, '')}`;
  }

  /** controla_stock/stock son opcionales: si no llegan, se deja agregar (el backend valida). */
  get sinStock(): boolean {
    return this.producto.controla_stock === true && (this.producto.stock ?? 0) <= 0;
  }

  get mensajePocoStock(): string | null {
    if (!this.producto.poco_stock || this.sinStock) return null;
    return this.producto.stock === 1
      ? 'Solo queda 1 unidad'
      : `Solo quedan ${this.producto.stock} unidades`;
  }

  /** Para productos solo mayoristas, la compra mínima. */
  private get cantidadAAgregar(): number {
    const minima = this.producto.cantidad_minima;
    return minima && minima > 1 ? minima : 1;
  }

  /**
   * Solo viajan producto, cantidad, modalidad y nº de cuotas:
   * el backend recalcula precios (nunca se confía en el navegador).
   */
  agregarAlCarrito(seleccion: SeleccionCompra = { modalidad: 'contado' }): void {
    if (this.agregando || this.sinStock) return;

    this.agregando = true;
    this.carritoService
      .agregar({
        car_idpro: this.producto.prd_id,
        car_idvarp: null,
        car_cantid: this.cantidadAAgregar,
        car_modpag: seleccion.modalidad,
        car_numcuo: seleccion.numero_cuotas ?? null,
      })
      .subscribe({
        next: () => {
          this.agregando = false;
          this.carritoUi.confirmarAgregado();
        },
        error: (err) => {
          this.agregando = false;
          this.toastr.error(this.mensajeError(err), 'No se pudo agregar');
        },
      });
  }

  /** Primer mensaje del 422 de Laravel: { errors: { campo: ["..."] } } */
  private mensajeError(err: any): string {
    const errores = err?.error?.errors;
    if (errores) {
      const primerCampo = Object.keys(errores)[0];
      return errores[primerCampo]?.[0] ?? 'Intenta nuevamente.';
    }
    return err?.error?.message ?? 'Intenta nuevamente.';
  }
}