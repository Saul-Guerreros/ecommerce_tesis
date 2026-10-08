import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import {
  BadgeModule, ButtonModule, CardModule, FormModule, ListGroupModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import {
  AtributoOption, CategoriaAtributo, SubcategoriaPlantilla,
} from '../models/categoria-atributo.model';
import { PlantillasFichaService } from '../service/plantillas-ficha.service';
import { FormCategoriaAtributoComponent } from '../form-categoria-atributo/form-categoria-atributo.component';
import { DeleteCategoriaAtributoComponent } from '../delete-categoria-atributo/delete-categoria-atributo.component';

@Component({
  selector: 'app-list-categoria-atributo',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule,
    SpinnerModule, FormModule, ListGroupModule,
    FormCategoriaAtributoComponent, DeleteCategoriaAtributoComponent,
  ],
  templateUrl: './list-categoria-atributo.component.html',
})
export class ListCategoriaAtributoComponent implements OnInit {
  // Panel izquierdo: subcategorías
  subcategorias: SubcategoriaPlantilla[] = [];
  filtroSubcategoria = '';
  cargandoSubcategorias = false;

  // Panel derecho: plantilla de la subcategoría elegida
  seleccionada: SubcategoriaPlantilla | null = null;
  plantilla: CategoriaAtributo[] = [];
  cargandoPlantilla = false;

  atributos: AtributoOption[] = [];

  formModalVisible = false;
  deleteModalVisible = false;
  itemSeleccionado: CategoriaAtributo | null = null;

  constructor(
    private plantillasService: PlantillasFichaService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.cargarSubcategorias();

    this.plantillasService.getConfig().subscribe({
      next: (res) => (this.atributos = res.atributos),
      error: (err) => this.handleError(err, 'No se pudieron cargar los atributos'),
    });
  }

  /** Filtro local: son pocas subcategorías, no hace falta ir al backend. */
  get subcategoriasFiltradas(): SubcategoriaPlantilla[] {
    const texto = this.filtroSubcategoria.trim().toLowerCase();
    if (!texto) return this.subcategorias;

    return this.subcategorias.filter(
      (s) => s.cat_nombre.toLowerCase().includes(texto) || s.padre_nombre.toLowerCase().includes(texto)
    );
  }

  /** IDs ya usados en la plantilla: el formulario los oculta para evitar duplicados. */
  get atributosUsados(): number[] {
    return this.plantilla.map((p) => p.cta_atbid!).filter((id) => id !== null);
  }

  cargarSubcategorias(): void {
    this.cargandoSubcategorias = true;
    this.plantillasService.getSubcategorias().subscribe({
      next: (res) => {
        this.subcategorias = res;
        this.cargandoSubcategorias = false;

        // Mantiene actualizados los contadores de la subcategoría abierta.
        if (this.seleccionada) {
          this.seleccionada = res.find((s) => s.cat_id === this.seleccionada!.cat_id) ?? null;
        }
      },
      error: (err) => {
        this.cargandoSubcategorias = false;
        this.handleError(err, 'No se pudieron cargar las subcategorías');
      },
    });
  }

  seleccionar(sub: SubcategoriaPlantilla): void {
    this.seleccionada = sub;
    this.cargarPlantilla();
  }

  cargarPlantilla(): void {
    if (!this.seleccionada) return;

    this.cargandoPlantilla = true;
    this.plantillasService.getPlantilla(this.seleccionada.cat_id).subscribe({
      next: (res) => {
        this.plantilla = res;
        this.cargandoPlantilla = false;
      },
      error: (err) => {
        this.cargandoPlantilla = false;
        this.handleError(err, 'No se pudo cargar la plantilla');
      },
    });
  }

  /** Tras guardar o eliminar se refrescan la tabla y los contadores del panel izquierdo. */
  refrescar(): void {
    this.cargarPlantilla();
    this.cargarSubcategorias();
  }

  abrirNuevo(): void {
    this.itemSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(item: CategoriaAtributo): void {
    this.itemSeleccionado = { ...item };
    this.formModalVisible = true;
  }

  abrirEliminar(item: CategoriaAtributo): void {
    this.itemSeleccionado = item;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.itemSeleccionado?.cta_id) return;

    this.plantillasService.delete(this.itemSeleccionado.cta_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Atributo quitado de la plantilla', 'Éxito');
        this.deleteModalVisible = false;
        this.refrescar();
      },
      error: (err) => {
        this.deleteModalVisible = false;
        this.handleError(err, 'No se pudo quitar el atributo');
      },
    });
  }

  private handleError(err: any, mensajePorDefecto: string): void {
    this.toastr.error(err?.error?.message ?? mensajePorDefecto, 'Error');
  }
}
