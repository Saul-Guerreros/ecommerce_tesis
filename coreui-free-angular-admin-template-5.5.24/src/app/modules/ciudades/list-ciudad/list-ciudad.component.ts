import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Ciudad, DepartamentoOption } from '../models/ciudad.model';
import { CiudadesService } from '../service/ciudades.service';
import { FormCiudadComponent } from '../form-ciudad/form-ciudad.component';
import { DeleteCiudadComponent } from '../delete-ciudad/delete-ciudad.component';

@Component({
  selector: 'app-list-ciudad',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule,
    FormCiudadComponent, DeleteCiudadComponent,
  ],
  templateUrl: './list-ciudad.component.html',
})
export class ListCiudadComponent implements OnInit {
  ciudades: Ciudad[] = [];
  departamentos: DepartamentoOption[] = [];
  cargando = false;

  search = '';
  filtroDepartamento: number | null = null;
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  ciudadSeleccionada: Ciudad | null = null;

  constructor(
    private ciudadesService: CiudadesService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.cargar();

    this.ciudadesService.getDepartamentos().subscribe({
      next: (res) => (this.departamentos = res),
      error: () => {},
    });

    this.searchSubject
      .pipe(debounceTime(400), distinctUntilChanged())
      .subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;
    this.ciudadesService
      .getAll({ search: this.search, departamento: this.filtroDepartamento, estado: this.filtroEstado })
      .subscribe({
        next: (res) => {
          this.ciudades = res;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudieron cargar las ciudades', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroDepartamento = null;
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.ciudadSeleccionada = null;
    this.formModalVisible = true;
  }

  abrirEditar(c: Ciudad): void {
    this.ciudadSeleccionada = { ...c };
    this.formModalVisible = true;
  }

  abrirEliminar(c: Ciudad): void {
    this.ciudadSeleccionada = c;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.ciudadSeleccionada?.ciu_id) return;

    this.ciudadesService.delete(this.ciudadSeleccionada.ciu_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Ciudad eliminada', 'Éxito');
        this.deleteModalVisible = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }

  cambiarEstado(c: Ciudad): void {
    this.ciudadesService.toggleEstado(c.ciu_id!).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito');
        this.cargar();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}