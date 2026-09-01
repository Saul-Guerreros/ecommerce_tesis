import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Sucursal, EmpresaOption } from '../models/sucursal.model';
import { SucursalesService } from '../service/sucursales.service';
import { FormSucursalComponent } from '../form-sucursal/form-sucursal.component';
import { DeleteSucursalComponent } from '../delete-sucursal/delete-sucursal.component';

@Component({
  selector: 'app-list-sucursal',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule,
    FormSucursalComponent, DeleteSucursalComponent,
  ],
  templateUrl: './list-sucursal.component.html',
})
export class ListSucursalComponent implements OnInit {
  sucursales: Sucursal[] = [];
  empresas: EmpresaOption[] = [];
  cargando = false;

  search = '';
  filtroEmpresa: number | null = null;
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  sucursalSeleccionada: Sucursal | null = null;

  constructor(
    private sucursalesService: SucursalesService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.cargar();

    this.sucursalesService.getConfig().subscribe({
      next: (res) => (this.empresas = res.empresas),
      error: () => {},
    });

    // Espera 400 ms sin tipeo antes de consultar: evita una petición por tecla.
    this.searchSubject
      .pipe(debounceTime(400), distinctUntilChanged())
      .subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;
    this.sucursalesService
      .getAll({ search: this.search, empresa: this.filtroEmpresa, estado: this.filtroEstado })
      .subscribe({
        next: (res) => {
          this.sucursales = res;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudieron cargar las sucursales', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEmpresa = null;
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.sucursalSeleccionada = null;
    this.formModalVisible = true;
  }

  abrirEditar(s: Sucursal): void {
    this.sucursalSeleccionada = { ...s };
    this.formModalVisible = true;
  }

  abrirEliminar(s: Sucursal): void {
    this.sucursalSeleccionada = s;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.sucursalSeleccionada?.suc_id) return;

    this.sucursalesService.delete(this.sucursalSeleccionada.suc_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Sucursal eliminada', 'Éxito');
        this.deleteModalVisible = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }

  cambiarEstado(s: Sucursal): void {
    this.sucursalesService.toggleEstado(s.suc_id!).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito');
        this.cargar();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}