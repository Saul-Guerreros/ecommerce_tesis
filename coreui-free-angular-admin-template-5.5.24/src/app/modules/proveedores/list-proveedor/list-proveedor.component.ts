import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Proveedor, TipoProveedorOption, CiudadOption } from '../models/proveedor.model';
import { ProveedoresService } from '../service/proveedores.service';
import { FormProveedorComponent } from '../form-proveedor/form-proveedor.component';
import { DeleteProveedorComponent } from '../delete-proveedor/delete-proveedor.component';

@Component({
  selector: 'app-list-proveedor',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FormProveedorComponent, DeleteProveedorComponent],
  templateUrl: './list-proveedor.component.html',
})
export class ListProveedorComponent implements OnInit {
  proveedores: Proveedor[] = [];
  tipos: TipoProveedorOption[] = [];
  ciudades: CiudadOption[] = [];
  cargando = false;

  search = '';
  filtroTipo: number | null = null;
  filtroCiudad: number | null = null;
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  proveedorSeleccionado: Proveedor | null = null;
  soloLectura = false;

  constructor(private service: ProveedoresService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();

    this.service.getConfig().subscribe({
      next: (res) => { this.tipos = res.tipos; this.ciudades = res.ciudades; },
      error: () => {},
    });

    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;
    this.service
      .getAll({ search: this.search, tipo: this.filtroTipo, ciudad: this.filtroCiudad, estado: this.filtroEstado })
      .subscribe({
        next: (res) => { this.proveedores = res; this.cargando = false; },
        error: () => { this.toastr.error('No se pudieron cargar los proveedores', 'Error'); this.cargando = false; },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroTipo = null;
    this.filtroCiudad = null;
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.proveedorSeleccionado = null;
    this.soloLectura = false;
    this.formModalVisible = true;
  }

  abrirEditar(p: Proveedor): void {
    this.proveedorSeleccionado = { ...p };
    this.soloLectura = false;
    this.formModalVisible = true;
  }

  abrirVer(p: Proveedor): void {
    this.proveedorSeleccionado = { ...p };
    this.soloLectura = true;
    this.formModalVisible = true;
  }

  abrirEliminar(p: Proveedor): void {
    this.proveedorSeleccionado = p;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.proveedorSeleccionado?.pro_id) return;
    this.service.delete(this.proveedorSeleccionado.pro_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Proveedor eliminado', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(p: Proveedor): void {
    this.service.toggleEstado(p.pro_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}