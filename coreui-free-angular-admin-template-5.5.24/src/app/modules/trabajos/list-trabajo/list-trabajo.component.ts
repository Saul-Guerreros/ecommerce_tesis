import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { TipoIvaOpcion, Trabajo } from '../models/trabajo.model';
import { TrabajosService } from '../service/trabajos.service';
import { FormTrabajoComponent } from '../form-trabajo/form-trabajo.component';
import { DeleteTrabajoComponent } from '../delete-trabajo/delete-trabajo.component';

@Component({
  selector: 'app-list-trabajo',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FormTrabajoComponent, DeleteTrabajoComponent],
  templateUrl: './list-trabajo.component.html',
})
export class ListTrabajoComponent implements OnInit, OnDestroy {
  trabajos: Trabajo[] = [];
  tiposIva: TipoIvaOpcion[] = [];
  cargando = false;

  search = '';
  filtroEstado = '';
  private searchSubject = new Subject<string>();
  private searchSub?: Subscription;

  formModalVisible = false;
  deleteModalVisible = false;
  trabajoSeleccionado: Trabajo | null = null;

  constructor(private service: TrabajosService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (cfg) => (this.tiposIva = cfg.tipos_iva),
      error: () => this.toastr.error('No se pudieron cargar los tipos de IVA', 'Error'),
    });
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
      next: (res) => { this.trabajos = res; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar los trabajos', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.trabajoSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(t: Trabajo): void {
    this.trabajoSeleccionado = { ...t };
    this.formModalVisible = true;
  }

  abrirEliminar(t: Trabajo): void {
    this.trabajoSeleccionado = t;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.trabajoSeleccionado?.trb_id) return;
    this.service.delete(this.trabajoSeleccionado.trb_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Eliminado', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(t: Trabajo): void {
    this.service.toggleEstado(t.trb_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}
