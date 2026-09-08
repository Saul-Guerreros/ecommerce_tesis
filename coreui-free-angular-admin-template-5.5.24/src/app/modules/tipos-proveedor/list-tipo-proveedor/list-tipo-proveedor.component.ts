import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { TipoProveedor } from '../models/tipo-proveedor.model';
import { TiposProveedorService } from '../service/tipos-proveedor.service';
import { FormTipoProveedorComponent } from '../form-tipo-proveedor/form-tipo-proveedor.component';
import { DeleteTipoProveedorComponent } from '../delete-tipo-proveedor/delete-tipo-proveedor.component';

@Component({
  selector: 'app-list-tipo-proveedor',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FormTipoProveedorComponent, DeleteTipoProveedorComponent],
  templateUrl: './list-tipo-proveedor.component.html',
})
export class ListTipoProveedorComponent implements OnInit {
  tipos: TipoProveedor[] = [];
  cargando = false;
  search = '';
  filtroEstado = '';
  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  tipoSeleccionado: TipoProveedor | null = null;

  constructor(private service: TiposProveedorService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();
    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;
    this.service.getAll({ search: this.search, estado: this.filtroEstado }).subscribe({
      next: (res) => { this.tipos = res; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar los tipos de proveedor', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.tipoSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(t: TipoProveedor): void {
    this.tipoSeleccionado = { ...t };
    this.formModalVisible = true;
  }

  abrirEliminar(t: TipoProveedor): void {
    this.tipoSeleccionado = t;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.tipoSeleccionado?.tpr_id) return;
    this.service.delete(this.tipoSeleccionado.tpr_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Eliminado', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(t: TipoProveedor): void {
    this.service.toggleEstado(t.tpr_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}