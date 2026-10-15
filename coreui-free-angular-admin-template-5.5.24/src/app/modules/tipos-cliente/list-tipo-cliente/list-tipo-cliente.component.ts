import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { TipoCliente } from '../models/tipo-cliente.model';
import { TiposClienteService } from '../service/tipos-cliente.service';
import { FormTipoClienteComponent } from '../form-tipo-cliente/form-tipo-cliente.component';
import { DeleteTipoClienteComponent } from '../delete-tipo-cliente/delete-tipo-cliente.component';

@Component({
  selector: 'app-list-tipo-cliente',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FormTipoClienteComponent, DeleteTipoClienteComponent],
  templateUrl: './list-tipo-cliente.component.html',
})
export class ListTipoClienteComponent implements OnInit {
  tipos: TipoCliente[] = [];
  cargando = false;
  search = '';
  filtroEstado = '';
  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  tipoSeleccionado: TipoCliente | null = null;

  constructor(private service: TiposClienteService, private toastr: ToastrService) {}

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
      error: () => { this.toastr.error('No se pudieron cargar los tipos de cliente', 'Error'); this.cargando = false; },
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

  abrirEditar(t: TipoCliente): void {
    this.tipoSeleccionado = { ...t };
    this.formModalVisible = true;
  }

  abrirEliminar(t: TipoCliente): void {
    this.tipoSeleccionado = t;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.tipoSeleccionado?.tpc_id) return;
    this.service.delete(this.tipoSeleccionado.tpc_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Eliminado', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(t: TipoCliente): void {
    this.service.toggleEstado(t.tpc_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}