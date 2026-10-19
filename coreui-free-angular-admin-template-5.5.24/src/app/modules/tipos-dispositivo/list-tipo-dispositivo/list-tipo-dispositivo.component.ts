import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { TipoDispositivo } from '../models/tipo-dispositivo.model';
import { TiposDispositivoService } from '../service/tipos-dispositivo.service';
import { FormTipoDispositivoComponent } from '../form-tipo-dispositivo/form-tipo-dispositivo.component';
import { DeleteTipoDispositivoComponent } from '../delete-tipo-dispositivo/delete-tipo-dispositivo.component';

@Component({
  selector: 'app-list-tipo-dispositivo',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FormTipoDispositivoComponent, DeleteTipoDispositivoComponent],
  templateUrl: './list-tipo-dispositivo.component.html',
})
export class ListTipoDispositivoComponent implements OnInit, OnDestroy {
  tipos: TipoDispositivo[] = [];
  cargando = false;
  search = '';
  filtroEstado = '';
  private searchSubject = new Subject<string>();
  private searchSub?: Subscription;

  formModalVisible = false;
  deleteModalVisible = false;
  tipoSeleccionado: TipoDispositivo | null = null;

  constructor(private service: TiposDispositivoService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();
    this.searchSub = this.searchSubject.pipe(debounceTime(400)).subscribe(() => this.cargar());
  }

  ngOnDestroy(): void {
    this.searchSub?.unsubscribe();
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;
    this.service.getAll({ search: this.search, estado: this.filtroEstado }).subscribe({
      next: (res) => { this.tipos = res; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar los tipos de dispositivo', 'Error'); this.cargando = false; },
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

  abrirEditar(t: TipoDispositivo): void {
    this.tipoSeleccionado = { ...t };
    this.formModalVisible = true;
  }

  abrirEliminar(t: TipoDispositivo): void {
    this.tipoSeleccionado = t;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.tipoSeleccionado?.tdi_id) return;
    this.service.delete(this.tipoSeleccionado.tdi_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Eliminado', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(t: TipoDispositivo): void {
    this.service.toggleEstado(t.tdi_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}
