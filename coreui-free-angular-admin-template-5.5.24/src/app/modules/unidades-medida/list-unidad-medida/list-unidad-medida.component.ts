import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { UnidadMedida } from '../models/unidad-medida.model';
import { UnidadesMedidaService } from '../service/unidades-medida.service';
import { FormUnidadMedidaComponent } from '../form-unidad-medida/form-unidad-medida.component';
import { DeleteUnidadMedidaComponent } from '../delete-unidad-medida/delete-unidad-medida.component';

@Component({
  selector: 'app-list-unidad-medida',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FormUnidadMedidaComponent, DeleteUnidadMedidaComponent],
  templateUrl: './list-unidad-medida.component.html',
})
export class ListUnidadMedidaComponent implements OnInit {
  unidades: UnidadMedida[] = [];
  cargando = false;
  search = '';
  filtroEstado = '';
  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  unidadSeleccionada: UnidadMedida | null = null;

  constructor(private service: UnidadesMedidaService, private toastr: ToastrService) {}

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
      next: (res) => { this.unidades = res; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar las unidades de medida', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.unidadSeleccionada = null;
    this.formModalVisible = true;
  }

  abrirEditar(u: UnidadMedida): void {
    this.unidadSeleccionada = { ...u };
    this.formModalVisible = true;
  }

  abrirEliminar(u: UnidadMedida): void {
    this.unidadSeleccionada = u;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.unidadSeleccionada?.umd_id) return;
    this.service.delete(this.unidadSeleccionada.umd_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Eliminada', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(u: UnidadMedida): void {
    this.service.toggleEstado(u.umd_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}