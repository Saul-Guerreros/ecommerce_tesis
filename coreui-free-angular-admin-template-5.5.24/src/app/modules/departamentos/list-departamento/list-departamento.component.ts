import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Departamento } from '../models/departamento.model';
import { DepartamentosService } from '../service/departamentos.service';
import { FormDepartamentoComponent } from '../form-departamento/form-departamento.component';
import { DeleteDepartamentoComponent } from '../delete-departamento/delete-departamento.component';

@Component({
  selector: 'app-list-departamento',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule,
    FormDepartamentoComponent, DeleteDepartamentoComponent,
  ],
  templateUrl: './list-departamento.component.html',
})
export class ListDepartamentoComponent implements OnInit {
  departamentos: Departamento[] = [];
  cargando = false;

  search = '';
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  departamentoSeleccionado: Departamento | null = null;

  constructor(
    private departamentosService: DepartamentosService,
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
    this.departamentosService
      .getAll({ search: this.search, estado: this.filtroEstado })
      .subscribe({
        next: (res) => {
          this.departamentos = res;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudieron cargar los departamentos', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.departamentoSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(d: Departamento): void {
    this.departamentoSeleccionado = { ...d };
    this.formModalVisible = true;
  }

  abrirEliminar(d: Departamento): void {
    this.departamentoSeleccionado = d;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.departamentoSeleccionado?.dep_id) return;

    this.departamentosService.delete(this.departamentoSeleccionado.dep_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Departamento eliminado', 'Éxito');
        this.deleteModalVisible = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }

  cambiarEstado(d: Departamento): void {
    this.departamentosService.toggleEstado(d.dep_id!).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito');
        this.cargar();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}