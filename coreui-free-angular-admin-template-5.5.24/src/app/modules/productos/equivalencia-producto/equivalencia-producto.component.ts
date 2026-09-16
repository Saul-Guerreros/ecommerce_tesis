import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule } from '@coreui/angular';
import { ProductoEquivalencia, UnidadMedidaOption } from '../models/producto.model';
import { EquivalenciasService } from '../service/equivalencias.service';

@Component({
  selector: 'app-equivalencia-producto',
  standalone: true,
  imports: [CommonModule, FormsModule, ButtonModule, FormModule],
  templateUrl: './equivalencia-producto.component.html',
})
export class EquivalenciaProductoComponent implements OnChanges {
  @Input() productoId: number | null = null;
  @Input() unidadBaseId: number | null = null;
  @Input() unidades: UnidadMedidaOption[] = []; // catálogo completo, ya lo carga form-producto
  @Input() soloLectura = false;

  equivalencias: ProductoEquivalencia[] = [];
  cargando = false;

  nuevaUnidad: number | null = null;
  nuevoFactor: number | null = null;
  guardando = false;

  constructor(
    private service: EquivalenciasService,
    private toastr: ToastrService
  ) {}

  // Unidades ofrecidas para agregar: todo el catálogo MENOS la unidad de
  // stock del producto y las que ya tienen equivalencia registrada.
  get unidadesDisponibles(): UnidadMedidaOption[] {
    const usadas = new Set(this.equivalencias.map((e) => e.peq_umdid));
    return this.unidades.filter((u) => u.umd_id !== this.unidadBaseId && !usadas.has(u.umd_id));
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['productoId'] && this.productoId) {
      this.cargar();
    }
  }

  cargar(): void {
    if (!this.productoId) return;
    this.cargando = true;
    this.service.getByProducto(this.productoId).subscribe({
      next: (res) => { this.equivalencias = res; this.cargando = false; },
      error: () => { this.cargando = false; this.toastr.error('No se pudieron cargar las equivalencias', 'Error'); },
    });
  }

  agregar(): void {
    if (!this.productoId || !this.nuevaUnidad) {
      this.toastr.error('Seleccione la unidad alternativa', 'Falta unidad');
      return;
    }
    if (!this.nuevoFactor || this.nuevoFactor <= 0) {
      this.toastr.error('El factor debe ser mayor a 0', 'Factor inválido');
      return;
    }

    this.guardando = true;
    this.service.create({ peq_prdid: this.productoId, peq_umdid: this.nuevaUnidad, peq_factor: this.nuevoFactor }).subscribe({
      next: () => {
        this.toastr.success('Equivalencia registrada correctamente', 'Éxito');
        this.nuevaUnidad = null;
        this.nuevoFactor = null;
        this.guardando = false;
        this.cargar();
      },
      error: (err) => {
        this.guardando = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar la equivalencia', 'Error');
      },
    });
  }

  eliminar(eq: ProductoEquivalencia): void {
    if (!eq.peq_id) return;
    this.service.delete(eq.peq_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Equivalencia eliminada', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'),
    });
  }
}