import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Rol } from '../models/rol.model';
import { RolesService } from '../service/roles.service';
import { FormRolComponent } from '../form-rol/form-rol.component';
import { DeleteRolComponent } from '../delete-rol/delete-rol.component';

@Component({
  selector: 'app-list-rol',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FormRolComponent, DeleteRolComponent],
  templateUrl: './list-rol.component.html',
})
export class ListRolComponent implements OnInit {
  roles: Rol[] = [];
  cargando = false;

  search = '';
  filtroEstado = '';
  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  rolSeleccionado: Rol | null = null;

  constructor(private service: RolesService, private toastr: ToastrService) {}

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
      next: (res) => { this.roles = res; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar los roles', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.rolSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(r: Rol): void {
    // Se pide el registro completo (con permisos) porque el listado no los trae.
    this.service.getById(r.rol_id!).subscribe({
      next: (rolCompleto) => {
        this.rolSeleccionado = rolCompleto;
        this.formModalVisible = true;
      },
      error: () => this.toastr.error('No se pudo cargar el rol', 'Error'),
    });
  }

  abrirEliminar(r: Rol): void {
    this.rolSeleccionado = r;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.rolSeleccionado?.rol_id) return;

    this.service.delete(this.rolSeleccionado.rol_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Rol eliminado', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(r: Rol): void {
    this.service.toggleEstado(r.rol_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}