import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';

/**
 * Control de unidades (− [n] +). Presentacional: no conoce precios ni
 * modalidades, solo mantiene el valor dentro de [minimo, maximo].
 *
 * Se usa con enlace bidireccional, una instancia por modalidad de compra,
 * para que las cantidades de Cuotas y Contado sean independientes:
 *
 *   <app-selector-cantidad [(valor)]="cantidadCuotas" [minimo]="min" />
 *
 * El valor tipeado se valida al confirmar (evento `change`: Enter o salir
 * del campo), no en cada tecla. Si se validara en cada tecla, con un mínimo
 * mayorista de 10 sería imposible escribir "10" (el "1" se corregiría a 10).
 */
@Component({
  selector: 'app-selector-cantidad',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './selector-cantidad.component.html',
  styleUrl: './selector-cantidad.component.css',
})
export class SelectorCantidadComponent {
  @Input() valor = 1;
  @Input() minimo = 1;
  /** null = sin tope (producto que no controla stock). */
  @Input() maximo: number | null = null;
  @Input() deshabilitado = false;
  @Input() etiqueta = 'Unidades';

  @Output() valorChange = new EventEmitter<number>();

  get enMinimo(): boolean {
    return this.valor <= this.minimo;
  }

  get enMaximo(): boolean {
    return this.maximo !== null && this.valor >= this.maximo;
  }

  disminuir(): void {
    this.emitir(this.valor - 1);
  }

  aumentar(): void {
    this.emitir(this.valor + 1);
  }

  alConfirmar(evento: Event): void {
    const campo = evento.target as HTMLInputElement;
    const escrito = Math.floor(Number(campo.value));
    const limpio = this.acotar(Number.isFinite(escrito) ? escrito : this.minimo);

    // Si el valor acotado coincide con el actual no hay cambio de estado y
    // Angular no repinta: se corrige el texto del campo a mano.
    campo.value = String(limpio);
    this.emitir(limpio);
  }

  private emitir(nuevo: number): void {
    const limpio = this.acotar(nuevo);

    if (limpio !== this.valor) {
      this.valorChange.emit(limpio);
    }
  }

  private acotar(n: number): number {
    const tope = this.maximo === null ? Infinity : Math.max(this.maximo, this.minimo);
    return Math.min(tope, Math.max(this.minimo, n));
  }
}