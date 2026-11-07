import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import {
  AlertModule, BadgeModule, ButtonModule, CardModule, FormModule, PaginationModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import { ResenaAdmin, ResumenResenasAdmin } from '../models/resena.model';
import { ResenasService } from '../service/resenas.service';
import { FormResenaComponent } from '../form-resena/form-resena.component';
import { OcultarResenaComponent } from '../ocultar-resena/ocultar-resena.component';
import { DeleteResenaComponent } from '../delete-resena/delete-resena.component';

@Component({
  selector: 'app-list-resena',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule,
    FormModule, AlertModule, PaginationModule, FormResenaComponent, OcultarResenaComponent, DeleteResenaComponent],
  templateUrl: './list-resena.component.html',
})
export class ListResenaComponent implements OnInit {
  readonly ESTRELLAS = [1, 2, 3, 4, 5];

  resenas: ResenaAdmin[] = [];
  resumen: ResumenResenasAdmin = { total: 0, ocultas: 0, sin_respuesta: 0 };
  cargando = false;

  search = '';
  filtroEstado = '';
  filtroCalificacion = '';
  filtroRespuesta = '';
  private searchSubject = new Subject<string>();

  currentPage = 1;
  lastPage = 1;
  perPage = 20;
  total = 0;

  formModalVisible = false;
  ocultarModalVisible = false;
  deleteModalVisible = false;
  resenaSeleccionada: ResenaAdmin | null = null;

  constructor(private service: ResenasService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar(1);
    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar(1));
  }

  onSearchChange(): void { this.searchSubject.next(this.search); }

  cargar(page: number = this.currentPage): void {
    this.cargando = true;
    this.service.getAll({
      search: this.search,
      estado: this.filtroEstado,
      calificacion: this.filtroCalificacion,
      respuesta: this.filtroRespuesta,
      page,
      per_page: this.perPage,
    }).subscribe({
      next: (res) => {
        this.resenas = res.data;
        this.resumen = res.resumen;
        this.currentPage = res.meta.current_page;
        this.lastPage = res.meta.last_page;
        this.perPage = res.meta.per_page;
        this.total = res.meta.total;
        this.cargando = false;
      },
      error: (err) => this.handleError(err, 'No se pudieron cargar las reseñas'),
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.filtroCalificacion = '';
    this.filtroRespuesta = '';
    this.cargar(1);
  }

  // ---------- Paginación (mismo patrón que Pedidos) ----------
  get rangoDesde(): number { return this.total === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1; }
  get rangoHasta(): number { return Math.min(this.currentPage * this.perPage, this.total); }

  get pagesArray(): number[] {
    const ventana = 5;
    let inicio = Math.max(1, this.currentPage - Math.floor(ventana / 2));
    const fin = Math.min(this.lastPage, inicio + ventana - 1);
    inicio = Math.max(1, fin - ventana + 1);
    return Array.from({ length: fin - inicio + 1 }, (_, i) => inicio + i);
  }

  cambiarPagina(page: number): void {
    if (page < 1 || page > this.lastPage || page === this.currentPage) return;
    this.cargar(page);
  }

  // ---------- Acciones ----------
  abrirResponder(r: ResenaAdmin): void { this.resenaSeleccionada = { ...r }; this.formModalVisible = true; }
  abrirEliminar(r: ResenaAdmin): void { this.resenaSeleccionada = r; this.deleteModalVisible = true; }

  /** Ocultar pide motivo (modal); volver a mostrar es directo. */
  cambiarEstado(r: ResenaAdmin): void {
    if (r.visible) {
      this.resenaSeleccionada = r;
      this.ocultarModalVisible = true;
      return;
    }
    this.service.cambiarEstado(r.res_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.handleError(err, 'No se pudo cambiar el estado'),
    });
  }

  confirmarEliminar(): void {
    if (!this.resenaSeleccionada) return;
    this.service.delete(this.resenaSeleccionada.res_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Eliminada', 'Éxito');
        this.deleteModalVisible = false;
        // Si era la última fila de la página, volver a la anterior.
        this.cargar(this.resenas.length === 1 && this.currentPage > 1 ? this.currentPage - 1 : this.currentPage);
      },
      error: (err) => { this.deleteModalVisible = false; this.handleError(err, 'No se pudo eliminar'); },
    });
  }

  private handleError(err: any, mensaje: string): void {
    this.cargando = false;
    this.toastr.error(err?.error?.message ?? mensaje, 'Error');
  }
}