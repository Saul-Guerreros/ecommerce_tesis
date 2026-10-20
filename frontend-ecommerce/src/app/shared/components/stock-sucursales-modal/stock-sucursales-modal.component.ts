import { CommonModule } from '@angular/common';
import {
  Component,
  EventEmitter,
  HostListener,
  Input,
  OnChanges,
  Output,
  SimpleChanges,
} from '@angular/core';
import { StockSucursal } from '../../../core/models/stock-sucursal.model';
import { StockSucursalService } from '../../../core/services/stock-sucursal.service';

/**
 * Modal "Ver Stock en Tiendas" del detalle de producto.
 *
 * Lista TODAS las sucursales activas que el administrador publicó en la
 * tienda web (suc_webstk), incluidas las que hoy están en cero: saber
 * dónde no hay unidades es parte de la información útil para el cliente.
 *
 * La carga es bajo demanda: se dispara cuando `visible` pasa a true. Se
 * vuelve a consultar en cada apertura porque el stock es un dato vivo;
 * solo se evita repetir la llamada si ya hay una en curso.
 */
@Component({
  selector: 'app-stock-sucursales-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './stock-sucursales-modal.component.html',
  styleUrl: './stock-sucursales-modal.component.css',
})
export class StockSucursalesModalComponent implements OnChanges {
  /** Slug del producto en pantalla. */
  @Input() slug: string | null = null;

  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();

  cargando = false;
  error = false;
  sucursales: StockSucursal[] = [];
  controlaStock = true;

  constructor(private stockSucursalService: StockSucursalService) {}

  ngOnChanges(cambios: SimpleChanges): void {
    if (cambios['visible'] && this.visible) {
      this.cargar();
    }
  }

  private cargar(): void {
    if (!this.slug || this.cargando) {
      return;
    }

    this.cargando = true;
    this.error = false;

    this.stockSucursalService.obtener(this.slug).subscribe({
      next: (res) => {
        this.sucursales = res.data.sucursales ?? [];
        this.controlaStock = res.data.producto?.controla_stock ?? true;
        this.cargando = false;
      },
      error: () => {
        this.sucursales = [];
        this.error = true;
        this.cargando = false;
      },
    });
  }

  reintentar(): void {
    this.cargar();
  }

  cerrar(): void {
    if (!this.visible) {
      return;
    }
    this.visible = false;
    this.visibleChange.emit(false);
  }

  @HostListener('document:keydown.escape')
  onEscape(): void {
    this.cerrar();
  }

  /** Sucursales con al menos una unidad: alimenta el resumen del encabezado. */
  get sucursalesConStock(): number {
    return this.sucursales.filter((s) => (s.existencia ?? 0) > 0).length;
  }

  hayStock(sucursal: StockSucursal): boolean {
    return (sucursal.existencia ?? 0) > 0;
  }

  /**
   * Texto del teléfono listo para `tel:`. Se deja solo dígitos y el "+"
   * inicial para que el enlace funcione en móvil con formatos como
   * "(021) 123-456".
   */
  telefonoEnlace(telefono: string | null): string {
    return (telefono ?? '').replace(/[^\d+]/g, '');
  }

  trackBySucursal(_: number, sucursal: StockSucursal): number {
    return sucursal.suc_id;
  }
}