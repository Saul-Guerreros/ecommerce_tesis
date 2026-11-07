import { CommonModule } from '@angular/common';
import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import { RouterModule } from '@angular/router';
import { ProductoPorResenar } from '../../../core/models/resena.model';
import { ResenaService } from '../../../core/services/resena.service';

/**
 * Tarjeta "Calificá tus productos" del detalle de un pedido.
 *
 * Solo aparece cuando el pedido está ENTREGADO. El botón lleva al detalle
 * del producto con ?resena=1, que abre la pestaña Reseñas con el
 * formulario: así existe un único formulario de reseña en toda la tienda.
 */
@Component({
  selector: 'app-calificar-productos',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './calificar-productos.component.html',
  styleUrl: './calificar-productos.component.css',
})
export class CalificarProductosComponent implements OnChanges {
  @Input({ required: true }) numero!: string;
  /** Código de estado_pedido; la consulta solo se hace si es ENTREGADO. */
  @Input() estado: string | null = null;

  readonly ESTRELLAS = [1, 2, 3, 4, 5];
  items: ProductoPorResenar[] = [];
  cargado = false;

  /** URLs de imagen que fallaron: se muestra el ícono en su lugar. */
  private imagenesRotas = new Set<string>();

  constructor(private servicio: ResenaService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['numero'] || changes['estado']) && this.numero && this.estado === 'ENTREGADO') {
      this.servicio.porPedido(this.numero).subscribe({
        next: (res) => {
          this.items = res.habilitado ? res.data : [];
          this.cargado = true;
        },
        error: () => {
          this.items = [];
          this.cargado = true;
        },
      });
    }
  }

  get pendientes(): number {
    return this.items.filter((i) => !i.resenada && i.slug).length;
  }

  muestraImagen(item: ProductoPorResenar): boolean {
    return !!item.imagen && !this.imagenesRotas.has(item.imagen);
  }

  marcarRota(item: ProductoPorResenar): void {
    if (item.imagen) this.imagenesRotas.add(item.imagen);
  }
}