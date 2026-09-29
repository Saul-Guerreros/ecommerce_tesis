import { CommonModule, CurrencyPipe } from '@angular/common';
import {
  Component,
  EventEmitter,
  HostBinding,
  Input,
  OnChanges,
  Output,
  SimpleChanges,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  CuotaOpcion,
  ProductoVitrina,
  SeleccionCompra,
} from '../../../core/models/vitrina.model';

/**
 * Bloque de compra de la tarjeta del Home. NO calcula cuotas (llegan
 * resueltas del backend) y NO agrega al carrito: emite la selección.
 */
@Component({
  selector: 'app-cuotero',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, FormsModule],
  templateUrl: './cuotero.component.html',
  styleUrl: './cuotero.component.css',
})
export class CuoteroComponent implements OnChanges {
  @Input({ required: true }) producto!: ProductoVitrina;
  @Input() procesando = false;
  @Input() sinStock = false;

  @Output() agregar = new EventEmitter<SeleccionCompra>();

  cuotaSeleccionada: CuotaOpcion | null = null;

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['producto']) {
      this.cuotaSeleccionada = this.cuotas[0] ?? null;
    }
  }

  get cuotas(): CuotaOpcion[] {
    return this.producto?.cuotas ?? [];
  }

  get tieneCuotas(): boolean {
    return this.cuotas.length > 0;
  }

  @HostBinding('class.sin-cuotas')
  get sinCuotas(): boolean {
    return !this.tieneCuotas;
  }

  /** Para un producto solo mayorista es su compra mínima, no 1. */
  get cantidad(): number {
    const minima = this.producto?.cantidad_minima ?? 1;
    return minima > 1 ? minima : 1;
  }

  get esPorVarias(): boolean {
    return this.cantidad > 1;
  }

  get totalContado(): number {
    return this.producto.precio_final * this.cantidad;
  }

  seleccionarCuota(valor: string): void {
    const cantidad = Number(valor);
    this.cuotaSeleccionada = this.cuotas.find((c) => c.cantidad === cantidad) ?? null;
  }

  comprarEnCuotas(): void {
    if (!this.cuotaSeleccionada || this.procesando || this.sinStock) {
      return;
    }

    this.agregar.emit({
      modalidad: 'credito',
      numero_cuotas: this.cuotaSeleccionada.cantidad,
    });
  }

  comprarAlContado(): void {
    if (this.procesando || this.sinStock) {
      return;
    }

    this.agregar.emit({ modalidad: 'contado' });
  }
}