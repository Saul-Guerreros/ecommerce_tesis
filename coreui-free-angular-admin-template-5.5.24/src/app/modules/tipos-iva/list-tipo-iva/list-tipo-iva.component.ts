import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { TipoIva } from '../models/tipo-iva.model';
import { TiposIvaService } from '../service/tipos-iva.service';
import { FormTipoIvaComponent } from '../form-tipo-iva/form-tipo-iva.component';
import { DeleteTipoIvaComponent } from '../delete-tipo-iva/delete-tipo-iva.component';

@Component({
  selector: 'app-list-tipo-iva',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule,
    FormTipoIvaComponent, DeleteTipoIvaComponent,
  ],
  templateUrl: './list-tipo-iva.component.html',
})
export class ListTipoIvaComponent implements OnInit {
  tipos: TipoIva[] = [];
  cargando = false;

  // Sin buscador: la tabla tiene tres filas fijas. Solo filtro de estado.
  filtroEstado = '';

  formModalVisible = false;
  deleteModalVisible = false;
  tipoSeleccionado: TipoIva | null = null;

  constructor(
    private tiposIvaService: TiposIvaService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.tiposIvaService.getAll({ estado: this.filtroEstado }).subscribe({
      next: (res) => {
        this.tipos = res;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar los tipos de IVA', 'Error');
        this.cargando = false;
      },
    });
  }

  limpiarFiltros(): void {
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.tipoSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(t: TipoIva): void {
    this.tipoSeleccionado = { ...t };
    this.formModalVisible = true;
  }

  abrirEliminar(t: TipoIva): void {
    this.tipoSeleccionado = t;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.tipoSeleccionado?.tiv_id) return;

    this.tiposIvaService.delete(this.tipoSeleccionado.tiv_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Tipo de IVA eliminado', 'Exito');
        this.deleteModalVisible = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }

  cambiarEstado(t: TipoIva): void {
    this.tiposIvaService.toggleEstado(t.tiv_id!).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Estado actualizado', 'Exito');
        this.cargar();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}
