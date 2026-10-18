import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { EntregaResumenCard } from '../../../core/models/resumen-compra.model';

/**
 * Tarjeta "Datos de Entrega". Aparece desde el Paso 2 en adelante (en el
 * Paso 1 todavía se está eligiendo, así que mostrarla ahí sería
 * redundante) y en el detalle de "Mis Pedidos".
 *
 * [editable] + (editar) son opcionales: sólo el Paso 2 los usa, para
 * volver al Paso 1 con el ícono de engranaje. En el Paso 3 y en Mis
 * Pedidos la entrega ya quedó fija al confirmar el pedido, así que no se
 * pasan.
 */
@Component({
  selector: 'app-datos-entrega-card',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './datos-entrega-card.component.html',
  styleUrl: './datos-entrega-card.component.css',
})
export class DatosEntregaCardComponent {
  @Input() entrega: EntregaResumenCard | null = null;
  @Input() editable = false;
  @Output() editar = new EventEmitter<void>();

  get esRetiro(): boolean {
    return this.entrega?.modalidad === 'retiro';
  }
}