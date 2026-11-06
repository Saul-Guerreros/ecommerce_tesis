import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { FallasService } from '../service/fallas.service';
import { FormFallaComponent } from '../form-falla/form-falla.component';
import { DeleteFallaComponent } from '../delete-falla/delete-falla.component';
import { Falla, TipoDispositivoOpcion, CategoriaFallaOpcion } from '../models/falla.model';

@Component({
  selector: 'app-list-falla',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FormFallaComponent, DeleteFallaComponent],
  templateUrl: './list-falla.component.html',
})
export class ListFallaComponent implements OnInit, OnDestroy {
  fallas: Falla[] = [];
  tiposDispositivo: TipoDispositivoOpcion[] = [];
  categorias: CategoriaFallaOpcion[] = [];
  cargando = false;

  search = '';
  filtroEstado = '';
  filtroTipo = '';   // '' = todos, 'GEN' = genéricas, o tdi_id
  private searchSubject = new Subject<string>();
  private searchSub?: Subscription;

  formModalVisible = false;
  deleteModalVisible = false;
  fallaSeleccionada: Falla | null = null;

  constructor(private service: FallasService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (cfg) => {
        this.tiposDispositivo = cfg.tipos_dispositivo;
        this.categorias = cfg.categorias ?? [];   // Punto 7
      },
      error: () => this.toastr.error('No se pudieron cargar los tipos de dispositivo', 'Error'),
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
    this.service.getAll({ search: this.search, estado: this.filtroEstado, tdi_id: this.filtroTipo }).subscribe({
      next: (res) => { this.fallas = res; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar las fallas', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.filtroTipo = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.fallaSeleccionada = null;
    this.formModalVisible = true;
  }

  abrirEditar(f: Falla): void {
    this.fallaSeleccionada = { ...f };
    this.formModalVisible = true;
  }

  abrirEliminar(f: Falla): void {
    this.fallaSeleccionada = f;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.fallaSeleccionada?.fll_id) return;
    this.service.delete(this.fallaSeleccionada.fll_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Eliminada', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(f: Falla): void {
    this.service.toggleEstado(f.fll_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}
