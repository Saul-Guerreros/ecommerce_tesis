import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Cargo } from '../models/cargo.model';
import { CargosService } from '../service/cargos.service';
import { FormCargoComponent } from '../form-cargo/form-cargo.component';
import { DeleteCargoComponent } from '../delete-cargo/delete-cargo.component';

@Component({
  selector: 'app-list-cargo',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FormCargoComponent, DeleteCargoComponent],
  templateUrl: './list-cargo.component.html',
})
export class ListCargoComponent implements OnInit {
  cargos: Cargo[] = [];
  cargando = false;
  search = '';
  filtroEstado = '';
  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  cargoSeleccionado: Cargo | null = null;

  constructor(private service: CargosService, private toastr: ToastrService) {}

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
      next: (res) => { this.cargos = res; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar los cargos', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.cargoSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(t: Cargo): void {
    this.cargoSeleccionado = { ...t };
    this.formModalVisible = true;
  }

  abrirEliminar(t: Cargo): void {
    this.cargoSeleccionado = t;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.cargoSeleccionado?.crg_id) return;
    this.service.delete(this.cargoSeleccionado.crg_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Eliminado', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(t: Cargo): void {
    this.service.toggleEstado(t.crg_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}