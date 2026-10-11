import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';
import { ToastrService } from 'ngx-toastr';
import {
  BadgeModule,
  ButtonModule,
  CardModule,
  FormModule,
  ModalModule,
  PaginationModule,
  SpinnerModule,
  TableModule,
} from '@coreui/angular';
import { AtributoService } from '../services/atributo.service';

@Component({
  selector: 'app-list-atributo',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ButtonModule,
    CardModule,
    TableModule,
    ModalModule,
    FormModule,
    BadgeModule,
    SpinnerModule,
    PaginationModule,
  ],
  templateUrl: './list-atributo.component.html',
})
export class ListAtributoComponent implements OnInit {
  // ---- Listado ----
  atributos: any[] = [];
  search = '';
  total = 0;
  currentPage = 1;
  pageSize = 25;

  isLoading$: Observable<boolean>;

  constructor(public atributoService: AtributoService, private toastr: ToastrService) {
    this.isLoading$ = this.atributoService.isLoading$;
  }

  ngOnInit(): void {
    this.loadList();
  }

  loadList(page: number = this.currentPage): void {
    this.atributoService.list(page, this.search).subscribe({
      next: (resp: any) => {
        this.atributos = resp.atributos;
        this.total = resp.total;
        this.currentPage = page;
      },
      error: () => this.toastr.error('No se pudieron cargar los atributos', 'Error'),
    });
  }

  get totalPages(): number {
    return Math.ceil(this.total / this.pageSize) || 1;
  }

  get pagesArray(): number[] {
    return Array.from({ length: this.totalPages }, (_, i) => i + 1);
  }

  searchTo(): void {
    this.loadList(1);
  }

  changePage(page: number): void {
    if (page < 1 || page > this.totalPages) return;
    this.loadList(page);
  }

  nombreTipo(tipo: number): string {
    switch (Number(tipo)) {
      case 1: return 'Texto';
      case 2: return 'Número';
      case 3: return 'Seleccionable';
      case 4: return 'Seleccionable Múltiple';
      default: return '';
    }
  }

  esNumero(tipo: number | string): boolean {
    return Number(tipo) === 2;
  }

  esSeleccionable(tipo: number): boolean {
    return Number(tipo) === 3 || Number(tipo) === 4;
  }

  /** Error de un campo devuelto por Laravel (422) o null. */
  private errorCampo(err: any, campo: string): string | null {
    return err?.status === 422 ? err?.error?.errors?.[campo]?.[0] ?? null : null;
  }

  /** Primer error de validación o el message del backend. */
  private mensajeError(err: any, porDefecto: string): string {
    const errors = err?.error?.errors;
    if (errors) {
      const primero = Object.values(errors)[0] as string[];
      if (primero?.length) return primero[0];
    }
    return err?.error?.message ?? porDefecto;
  }

  // ---- Crear ----
  showCreate = false;
  createNombre = '';
  createTipo = 1;
  createUnidad = '';
  createErrorNombre: string | null = null;
  guardandoCreate = false;

  openCreate(): void {
    this.createNombre = '';
    this.createTipo = 1;
    this.createUnidad = '';
    this.createErrorNombre = null;
    this.guardandoCreate = false;
    this.showCreate = true;
  }

  guardarCreate(): void {
    if (!this.createNombre.trim()) {
      this.createErrorNombre = 'El nombre del atributo es obligatorio.';
      return;
    }

    const data = {
      atb_nombre: this.createNombre.trim(),
      atb_tipo: Number(this.createTipo),
      atb_unidad: this.esNumero(this.createTipo) ? this.createUnidad.trim() || null : null,
      atb_estado: 1,
    };

    this.guardandoCreate = true;
    this.atributoService.create(data).subscribe({
      next: (resp: any) => {
        this.toastr.success(resp?.message ?? 'Atributo registrado', 'Éxito');
        this.showCreate = false;
        this.guardandoCreate = false;
        this.loadList(1);
      },
      error: (err) => {
        this.guardandoCreate = false;
        this.createErrorNombre = this.errorCampo(err, 'atb_nombre');
        this.toastr.error(this.mensajeError(err, 'No se pudo registrar el atributo'), 'Error');
      },
    });
  }

  // ---- Editar ----
  showEdit = false;
  editAtributo: any = null;
  editNombre = '';
  editTipo = 1;
  editUnidad = '';
  editErrorNombre: string | null = null;
  guardandoEdit = false;

  openEdit(atributo: any): void {
    this.editAtributo = atributo;
    this.editNombre = atributo.atb_nombre;
    this.editTipo = Number(atributo.atb_tipo);
    this.editUnidad = atributo.atb_unidad ?? '';
    this.editErrorNombre = null;
    this.guardandoEdit = false;
    this.showEdit = true;
  }

  guardarEdit(): void {
    if (!this.editNombre.trim()) {
      this.editErrorNombre = 'El nombre del atributo es obligatorio.';
      return;
    }

    const data = {
      atb_nombre: this.editNombre.trim(),
      atb_tipo: Number(this.editTipo),
      atb_unidad: this.esNumero(this.editTipo) ? this.editUnidad.trim() || null : null,
      atb_estado: this.editAtributo.atb_estado ?? 1,
    };

    this.guardandoEdit = true;
    this.atributoService.update(this.editAtributo.atb_id, data).subscribe({
      next: (resp: any) => {
        this.toastr.success(resp?.message ?? 'Atributo actualizado', 'Éxito');
        this.showEdit = false;
        this.guardandoEdit = false;
        this.loadList();
      },
      error: (err) => {
        this.guardandoEdit = false;
        this.editErrorNombre = this.errorCampo(err, 'atb_nombre');
        this.toastr.error(this.mensajeError(err, 'No se pudo actualizar el atributo'), 'Error');
      },
    });
  }

  // ---- Eliminar ----
  showDelete = false;
  deleteAtributo: any = null;

  openDelete(atributo: any): void {
    this.deleteAtributo = atributo;
    this.showDelete = true;
  }

  confirmarDelete(): void {
    if (!this.deleteAtributo?.atb_id) return;

    this.atributoService.delete(this.deleteAtributo.atb_id).subscribe({
      next: (resp: any) => {
        this.toastr.success(resp?.message ?? 'Atributo eliminado', 'Éxito');
        this.showDelete = false;
        // Si era el último de la página, retroceder una
        const page = this.atributos.length === 1 && this.currentPage > 1 ? this.currentPage - 1 : this.currentPage;
        this.loadList(page);
      },
      // 409: el atributo está en una Plantilla de Ficha Técnica
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar el atributo', 'Error');
        this.showDelete = false;
      },
    });
  }

  // ---- Propiedades ----
  showPropiedades = false;
  propAtributo: any = null;
  propiedades: any[] = [];
  propNombre = '';
  propTipoAccion = 1; // 1 Normal, 2 Color
  propCodigo = '#000000';
  propErrorNombre: string | null = null;

  openPropiedades(atributo: any): void {
    this.propAtributo = atributo;
    this.propiedades = atributo.propiedades ? [...atributo.propiedades] : [];
    this.propNombre = '';
    this.propTipoAccion = 1;
    this.propCodigo = '#000000';
    this.propErrorNombre = null;
    this.showPropiedades = true;
  }

  guardarPropiedad(): void {
    if (!this.propNombre.trim()) {
      this.propErrorNombre = 'El nombre de la propiedad es obligatorio.';
      return;
    }

    const data = {
      prp_atbid: this.propAtributo.atb_id,
      prp_nombre: this.propNombre.trim(),
      prp_codigo: Number(this.propTipoAccion) === 2 ? this.propCodigo : '',
    };

    this.atributoService.createPropiedad(data).subscribe({
      next: (resp: any) => {
        this.toastr.success(resp?.message ?? 'Propiedad agregada', 'Éxito');
        this.propErrorNombre = null;
        this.propiedades.unshift(resp.propiedad);
        this.propAtributo.propiedades = [...this.propiedades];
        this.propNombre = '';
      },
      error: (err) => {
        this.propErrorNombre = this.errorCampo(err, 'prp_nombre');
        this.toastr.error(this.mensajeError(err, 'No se pudo agregar la propiedad'), 'Error');
      },
    });
  }

  eliminarPropiedad(propiedad: any): void {
    this.atributoService.deletePropiedad(propiedad.prp_id).subscribe({
      next: (resp: any) => {
        this.toastr.success(resp?.message ?? 'Propiedad eliminada', 'Éxito');
        this.propiedades = this.propiedades.filter((p) => p.prp_id !== propiedad.prp_id);
        this.propAtributo.propiedades = [...this.propiedades];
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo eliminar la propiedad', 'Error'),
    });
  }
}