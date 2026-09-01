import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { TipoComprobante } from '../models/tipo-comprobante.model';
import { TiposComprobanteService } from '../service/tipos-comprobante.service';
import { FormTipoComprobanteComponent } from '../form-tipo-comprobante/form-tipo-comprobante.component';
import { DeleteTipoComprobanteComponent } from '../delete-tipo-comprobante/delete-tipo-comprobante.component';

@Component({
  selector: 'app-list-tipo-comprobante',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule,
    FormTipoComprobanteComponent, DeleteTipoComprobanteComponent,
  ],
  templateUrl: './list-tipo-comprobante.component.html',
})
export class ListTipoComprobanteComponent implements OnInit {
  tipos: TipoComprobante[] = [];
  cargando = false;

  search = '';
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  tipoSeleccionado: TipoComprobante | null = null;

  constructor(
    private tiposComprobanteService: TiposComprobanteService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.cargar();

    this.searchSubject
      .pipe(debounceTime(400), distinctUntilChanged())
      .subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;
    this.tiposComprobanteService
      .getAll({ search: this.search, estado: this.filtroEstado })
      .subscribe({
        next: (res) => {
          this.tipos = res;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudieron cargar los tipos de comprobante', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.cargar();
  }

  // Traduce el signo almacenado a la etiqueta que ve el usuario.
  etiquetaEfecto(valor?: number): string {
    if (valor === 1) return 'Suma';
    if (valor === -1) return 'Resta';
    return 'Neutro';
  }

  colorEfecto(valor?: number): string {
    if (valor === 1) return 'success';
    if (valor === -1) return 'danger';
    return 'secondary';
  }

  abrirNuevo(): void {
    this.tipoSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(t: TipoComprobante): void {
    this.tipoSeleccionado = { ...t };
    this.formModalVisible = true;
  }

  abrirEliminar(t: TipoComprobante): void {
    this.tipoSeleccionado = t;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.tipoSeleccionado?.tco_id) return;

    this.tiposComprobanteService.delete(this.tipoSeleccionado.tco_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Tipo de comprobante eliminado', 'Exito');
        this.deleteModalVisible = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }

  cambiarEstado(t: TipoComprobante): void {
    this.tiposComprobanteService.toggleEstado(t.tco_id!).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Estado actualizado', 'Exito');
        this.cargar();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}
