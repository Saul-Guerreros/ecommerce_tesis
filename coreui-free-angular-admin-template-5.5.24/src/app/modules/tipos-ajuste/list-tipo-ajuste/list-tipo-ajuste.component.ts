import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { TipoAjuste } from '../models/tipo-ajuste.model';
import { TiposAjusteService } from '../service/tipos-ajuste.service';
import { FormTipoAjusteComponent } from '../form-tipo-ajuste/form-tipo-ajuste.component';
import { DeleteTipoAjusteComponent } from '../delete-tipo-ajuste/delete-tipo-ajuste.component';
@Component({
  selector: 'app-list-tipo-ajuste',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FormTipoAjusteComponent, DeleteTipoAjusteComponent],
  templateUrl: './list-tipo-ajuste.component.html',
})
export class ListTipoAjusteComponent implements OnInit {
  tipos: TipoAjuste[] = [];
  cargando = false;
  search = '';
  filtroEstado = '';
  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  tipoSeleccionado: TipoAjuste | null = null;

  constructor(private service: TiposAjusteService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();
    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar());
  }

  onSearchChange(): void { this.searchSubject.next(this.search); }

  cargar(): void {
    this.cargando = true;
    this.service.getAll({ search: this.search, estado: this.filtroEstado }).subscribe({
      next: (res) => { this.tipos = res; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar los tipos de ajuste', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void { this.search = ''; this.filtroEstado = ''; this.cargar(); }
  abrirNuevo(): void { this.tipoSeleccionado = null; this.formModalVisible = true; }
  abrirEditar(t: TipoAjuste): void { this.tipoSeleccionado = { ...t }; this.formModalVisible = true; }
  abrirEliminar(t: TipoAjuste): void { this.tipoSeleccionado = t; this.deleteModalVisible = true; }

  confirmarEliminar(): void {
    if (!this.tipoSeleccionado?.tpa_id) return;
    this.service.delete(this.tipoSeleccionado.tpa_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Eliminado', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(t: TipoAjuste): void {
    this.service.toggleEstado(t.tpa_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}