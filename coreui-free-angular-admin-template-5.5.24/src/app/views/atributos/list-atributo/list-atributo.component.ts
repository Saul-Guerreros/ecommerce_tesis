import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';
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

  constructor(public atributoService: AtributoService) {
    this.isLoading$ = this.atributoService.isLoading$;
  }

  ngOnInit(): void {
    this.loadList();
  }

  loadList(page: number = 1): void {
    this.atributoService.list(page, this.search).subscribe((resp: any) => {
      this.atributos = resp.atributos;
      this.total = resp.total;
      this.currentPage = page;
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
      case 1:
        return 'Texto';
      case 2:
        return 'Número';
      case 3:
        return 'Seleccionable';
      case 4:
        return 'Seleccionable Múltiple';
      default:
        return '';
    }
  }

  esNumero(tipo: number | string): boolean {
    return Number(tipo) === 2;
  }

  esSeleccionable(tipo: number): boolean {
    return Number(tipo) === 3 || Number(tipo) === 4;
  }

  // ---- Crear ----
  showCreate = false;
  createNombre = '';
  createTipo = 1;
  createUnidad = '';
  createError = '';

  openCreate(): void {
    this.createNombre = '';
    this.createTipo = 1;
    this.createUnidad = '';
    this.createError = '';
    this.showCreate = true;
  }

  guardarCreate(): void {
    if (!this.createNombre || !this.createTipo) {
      this.createError = 'Todos los campos son necesarios';
      return;
    }
    const data = {
      atb_nombre: this.createNombre,
      atb_tipo: this.createTipo,
      atb_unidad: this.esNumero(this.createTipo) ? this.createUnidad.trim() || null : null,
      atb_estado: 1,
    };
    this.atributoService.create(data).subscribe((resp: any) => {
      if (resp.message === 403) {
        this.createError = 'El nombre del atributo ya existe';
        return;
      }
      this.atributos.unshift(resp.atributo);
      this.showCreate = false;
    });
  }

  // ---- Editar ----
  showEdit = false;
  editAtributo: any = null;
  editNombre = '';
  editTipo = 1;
  editUnidad = '';
  editError = '';

  openEdit(atributo: any): void {
    this.editAtributo = atributo;
    this.editNombre = atributo.atb_nombre;
    this.editTipo = atributo.atb_tipo;
    this.editUnidad = atributo.atb_unidad ?? '';
    this.editError = '';
    this.showEdit = true;
  }

  guardarEdit(): void {
    if (!this.editNombre || !this.editTipo) {
      this.editError = 'Todos los campos son necesarios';
      return;
    }
    const data = {
      atb_nombre: this.editNombre,
      atb_tipo: this.editTipo,
      atb_unidad: this.esNumero(this.editTipo) ? this.editUnidad.trim() || null : null,
      atb_estado: this.editAtributo.atb_estado ?? 1,
    };
    this.atributoService.update(this.editAtributo.atb_id, data).subscribe((resp: any) => {
      if (resp.message === 403) {
        this.editError = 'El nombre del atributo ya existe';
        return;
      }
      const idx = this.atributos.findIndex((a) => a.atb_id === resp.atributo.atb_id);
      if (idx !== -1) this.atributos[idx] = resp.atributo;
      this.showEdit = false;
    });
  }

  // ---- Eliminar ----
  showDelete = false;
  deleteAtributo: any = null;
  deleteError = '';

  openDelete(atributo: any): void {
    this.deleteAtributo = atributo;
    this.deleteError = '';
    this.showDelete = true;
  }

  confirmarDelete(): void {
    this.atributoService.delete(this.deleteAtributo.atb_id).subscribe({
      next: () => {
        this.atributos = this.atributos.filter((a) => a.atb_id !== this.deleteAtributo.atb_id);
        this.showDelete = false;
      },
      // 409: el atributo está en una Plantilla de Ficha Técnica.
      error: (err) => (this.deleteError = err?.error?.message ?? 'No se pudo eliminar el atributo'),
    });
  }

  // ---- Propiedades ----
  showPropiedades = false;
  propAtributo: any = null;
  propiedades: any[] = [];
  propNombre = '';
  propTipoAccion = 1; // 1 Normal, 2 Color
  propCodigo = '';
  propError = '';

  openPropiedades(atributo: any): void {
    this.propAtributo = atributo;
    this.propiedades = atributo.propiedades ? [...atributo.propiedades] : [];
    this.propNombre = '';
    this.propTipoAccion = 1;
    this.propCodigo = '';
    this.propError = '';
    this.showPropiedades = true;
  }

  guardarPropiedad(): void {
    if (!this.propNombre) {
      this.propError = 'El nombre es obligatorio';
      return;
    }
    if (this.propTipoAccion == 2 && !this.propCodigo) {
      this.propError = 'Necesitas seleccionar un color';
      return;
    }
    const data = {
      prp_atbid: this.propAtributo.atb_id,
      prp_nombre: this.propNombre,
      prp_codigo: this.propTipoAccion == 2 ? this.propCodigo : '',
    };
    this.atributoService.createPropiedad(data).subscribe((resp: any) => {
      if (resp.message === 403) {
        this.propError = 'La propiedad ya existe para este atributo';
        return;
      }
      this.propError = '';
      this.propiedades.unshift(resp.propiedad);
      this.propAtributo.propiedades = this.propiedades;
      this.propNombre = '';
      this.propCodigo = '';
    });
  }

  eliminarPropiedad(propiedad: any): void {
    this.atributoService.deletePropiedad(propiedad.prp_id).subscribe(() => {
      this.propiedades = this.propiedades.filter((p) => p.prp_id !== propiedad.prp_id);
      this.propAtributo.propiedades = this.propiedades;
    });
  }
}
