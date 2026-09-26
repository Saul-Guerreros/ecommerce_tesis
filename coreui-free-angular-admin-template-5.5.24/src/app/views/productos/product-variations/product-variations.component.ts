import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import {
  ButtonModule,
  CardModule,
  FormModule,
  GridModule,
  ModalModule,
  SpinnerModule,
  TableModule,
  BadgeModule,
} from '@coreui/angular';
import { IconModule } from '@coreui/icons-angular';
import { ToastrService } from 'ngx-toastr';
import { environment } from '../../../../environments/environment';

import { ProductVariationsService } from './services/product-variations.service';
import {
  AtributoVariacion,
  Atributo,
  Especificacion,
  Propiedad,
  PropiedadEspecificacion,
  Variacion,
} from './models/product-variation.model';

@Component({
  selector: 'app-product-variations',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ButtonModule,
    CardModule,
    FormModule,
    GridModule,
    ModalModule,
    SpinnerModule,
    TableModule,
    BadgeModule,
    IconModule,
  ],
  templateUrl: './product-variations.component.html',
  styleUrls: ['./product-variations.component.scss'],
})
export class ProductVariationsComponent implements OnInit {
  productId!: string;
  productTitle = '';
  productSku = '';

  isLoading$!: Observable<boolean>;

  // ==== ESPECIFICACIONES ====
  atributosEspecificaciones: Atributo[] = []; // TODO: cargar desde tu endpoint de Atributos ya existente
  especificaciones: Especificacion[] = [];

  specAtributoId = '';
  specTipoAtributo = 0;
  specPropiedades: PropiedadEspecificacion[] = [];
  specPropiedadId = ''; // usado en tipo 1/2/3 (una sola propiedad ancla/valor)
  specPropiedadIds: number[] = []; // usado en tipo 4 (multiselección)
  specValorLibre = '';
  specValorNumero: number | null = null;

  editEspVisible = false;
  editEsp: Especificacion | null = null;
  editEspAtributoId = '';
  editEspTipoAtributo = 0;
  editEspPropiedades: PropiedadEspecificacion[] = [];
  editEspPropiedadId = '';
  editEspValorLibre = '';
  editEspValorNumero: number | null = null;

  deleteEspVisible = false;
  deleteEsp: Especificacion | null = null;

  // ==== VARIACIONES ====
  atributosVariaciones: AtributoVariacion[] = [];
  variaciones: Variacion[] = [];

  // atributos ya "fijados" por la primera variación del producto (si existen)
  atributosFijos: AtributoVariacion[] = [];
  // selección temporal cuando aún no hay variaciones (elige qué atributos combinar)
  atributosSeleccionadosIds: number[] = [];

  varPropiedadPorAtributo: { [atb_id: number]: string } = {};
  varPrecioAdicional = 0;
  varStock = 0;
  varStockMinimo = 0;
  varEstado = true;

  editVarVisible = false;
  editVar: Variacion | null = null;
  editVarPropiedadPorAtributo: { [atb_id: number]: string } = {};
  editVarPrecioAdicional = 0;
  editVarStock = 0;
  editVarStockMinimo = 0;
  editVarEstado = true;

  deleteVarVisible = false;
  deleteVar: Variacion | null = null;

  constructor(
    private service: ProductVariationsService,
    private route: ActivatedRoute,
    private http: HttpClient,
    private toastr: ToastrService
  ) {
    this.isLoading$ = this.service.isLoading$;
  }

  ngOnInit(): void {
    this.productId = this.route.snapshot.paramMap.get('id') ?? '';

    this.loadProduct();
    this.loadAtributosEspecificaciones();
    this.loadConfigVariaciones();
    this.loadEspecificaciones();
    this.loadVariaciones();
  }

  loadProduct(): void {
    // GET /productos/{id} -> { data: { prd_id, prd_codinv, ..., publicacion_web: {...} | null } }
    // (ProductoResource). prd_titulo se movió a publicacion_web.pub_titulo:
    // si el producto todavía no fue publicado, se muestra el código como
    // referencia visual en el header de esta pantalla.
    this.service.showProduct(this.productId).subscribe((resp: any) => {
      this.productTitle = resp.data?.publicacion_web?.pub_titulo ?? resp.data?.prd_codinv ?? '';
      this.productSku = resp.data?.prd_codinv ?? '';
    });
  }

  /**
   * GET /admin/atributos -> { total, atributos: [{ atb_id, atb_nombre, atb_tipo, propiedades: [...] }] }
   * (AtributoController::index, paginado con ->paginate(25))
   */
  loadAtributosEspecificaciones(): void {
    this.http.get<any>(`${environment.apiUrl}/admin/atributos`).subscribe({
      next: (resp) => (this.atributosEspecificaciones = resp.atributos ?? []),
      error: () => {
        this.toastr.error('No se pudieron cargar los atributos', 'Error');
      },
    });
  }

  loadConfigVariaciones(): void {
    this.service.configVariaciones().subscribe((resp) => {
      this.atributosVariaciones = resp.atributos_variaciones;
    });
  }

  loadEspecificaciones(): void {
    this.service.listEspecificaciones(this.productId).subscribe((resp) => {
      this.especificaciones = resp.especificaciones;
    });
  }

  loadVariaciones(): void {
    this.service.listVariaciones(this.productId).subscribe((resp) => {
      this.variaciones = resp.variaciones;
      this.actualizarAtributosFijos();
    });
  }

  /** Si ya hay variaciones creadas, los atributos usados quedan fijos para las siguientes. */
  private actualizarAtributosFijos(): void {
    if (this.variaciones.length === 0) {
      this.atributosFijos = [];
      return;
    }
    const primera = this.variaciones[0];
    const atbIdsUsados = [...new Set(primera.propiedades.map((p) => p.atb_id))];
    this.atributosFijos = this.atributosVariaciones.filter((a) => atbIdsUsados.includes(a.atb_id));
    this.varPropiedadPorAtributo = {};
  }

  getNombreCombinacion(propiedades: { prp_nombre: string }[]): string {
    return propiedades.map((p) => p.prp_nombre).join(' + ');
  }

  // ==================== ESPECIFICACIONES: CREAR ====================

  changeSpecAtributo(): void {
    this.specPropiedadId = '';
    this.specPropiedadIds = [];
    this.specValorLibre = '';
    this.specValorNumero = null;

    const atributo = this.atributosEspecificaciones.find((a) => a.atb_id === +this.specAtributoId);
    this.specTipoAtributo = atributo?.atb_tipo ?? 0;
    this.specPropiedades = (atributo as any)?.propiedades ?? [];
  }

  onSpecMultiSelectChange(event: Event): void {
    const select = event.target as HTMLSelectElement;
    this.specPropiedadIds = Array.from(select.selectedOptions).map((opt) => +opt.value);
  }

  saveEspecificacion(): void {
    if (!this.specAtributoId) {
      this.toastr.error('Selecciona un atributo', 'Validación');
      return;
    }

    let propiedadIds: number[] = [];
    if (this.specTipoAtributo === 4) {
      if (this.specPropiedadIds.length === 0) {
        this.toastr.error('Selecciona al menos una propiedad', 'Validación');
        return;
      }
      propiedadIds = this.specPropiedadIds;
    } else {
      if (!this.specPropiedadId) {
        this.toastr.error('Selecciona una propiedad', 'Validación');
        return;
      }
      propiedadIds = [+this.specPropiedadId];
    }

    if (this.specTipoAtributo === 1 && !this.specValorLibre) {
      this.toastr.error('El valor de texto es obligatorio para este atributo', 'Validación');
      return;
    }
    if (this.specTipoAtributo === 2 && this.specValorNumero === null) {
      this.toastr.error('El valor numérico es obligatorio para este atributo', 'Validación');
      return;
    }

    const data = {
      producto_id: this.productId,
      atributo_id: this.specAtributoId,
      propiedad_ids: propiedadIds,
      valor_libre: this.specTipoAtributo === 1 ? this.specValorLibre : null,
      valor_numero: this.specTipoAtributo === 2 ? this.specValorNumero : null,
    };

    this.service.createEspecificacion(data).subscribe((resp) => {
      if (resp.message === 403) {
        this.toastr.error(resp.message_text, 'Validación');
        return;
      }
      this.toastr.success('Especificación registrada correctamente', 'Éxito');
      this.especificaciones.unshift(...resp.especificaciones);
      this.specAtributoId = '';
      this.specPropiedadId = '';
      this.specPropiedadIds = [];
      this.specValorLibre = '';
      this.specValorNumero = null;
      this.specTipoAtributo = 0;
      this.specPropiedades = [];
    });
  }

  getValorEspecificacion(e: Especificacion): string {
    if (e.esp_valibr) return e.esp_valibr;
    if (e.esp_valnum !== null && e.esp_valnum !== undefined) return String(e.esp_valnum);
    return e.propiedad?.prp_nombre ?? '---';
  }

  // ==================== ESPECIFICACIONES: EDITAR ====================

  openEditEspecificacion(item: Especificacion): void {
    this.editEsp = item;
    this.editEspAtributoId = String(item.esp_atbid);
    this.editEspPropiedadId = String(item.esp_prpid);
    this.editEspValorLibre = item.esp_valibr ?? '';
    this.editEspValorNumero = item.esp_valnum ?? null;

    const atributo = this.atributosEspecificaciones.find((a) => a.atb_id === item.esp_atbid);
    this.editEspTipoAtributo = atributo?.atb_tipo ?? 0;
    this.editEspPropiedades = (atributo as any)?.propiedades ?? [];

    this.editEspVisible = true;
  }

  changeEditEspAtributo(): void {
    this.editEspPropiedadId = '';
    const atributo = this.atributosEspecificaciones.find((a) => a.atb_id === +this.editEspAtributoId);
    this.editEspTipoAtributo = atributo?.atb_tipo ?? 0;
    this.editEspPropiedades = (atributo as any)?.propiedades ?? [];
  }

  saveEditEspecificacion(): void {
    if (!this.editEsp) return;
    if (!this.editEspAtributoId || !this.editEspPropiedadId) {
      this.toastr.error('Llene los campos necesarios', 'Validación');
      return;
    }

    const data = {
      producto_id: this.productId,
      atributo_id: this.editEspAtributoId,
      propiedad_id: this.editEspPropiedadId,
      valor_libre: this.editEspTipoAtributo === 1 ? this.editEspValorLibre : null,
      valor_numero: this.editEspTipoAtributo === 2 ? this.editEspValorNumero : null,
    };

    this.service.updateEspecificacion(this.editEsp.esp_id, data).subscribe((resp) => {
      if (resp.message === 403) {
        this.toastr.error(resp.message_text, 'Validación');
        return;
      }
      this.toastr.success('Especificación actualizada correctamente', 'Éxito');
      const index = this.especificaciones.findIndex((e) => e.esp_id === resp.especificacion.esp_id);
      if (index !== -1) this.especificaciones[index] = resp.especificacion;
      this.editEspVisible = false;
    });
  }

  openDeleteEspecificacion(item: Especificacion): void {
    this.deleteEsp = item;
    this.deleteEspVisible = true;
  }

  confirmDeleteEspecificacion(): void {
    if (!this.deleteEsp) return;
    this.service.deleteEspecificacion(this.deleteEsp.esp_id).subscribe(() => {
      this.especificaciones = this.especificaciones.filter((e) => e.esp_id !== this.deleteEsp!.esp_id);
      this.toastr.success('Especificación eliminada', 'Éxito');
      this.deleteEspVisible = false;
    });
  }

  // ==================== VARIACIONES: CREAR ====================

  toggleAtributoSeleccionado(atb_id: number, checked: boolean): void {
    if (checked) {
      this.atributosSeleccionadosIds.push(atb_id);
    } else {
      this.atributosSeleccionadosIds = this.atributosSeleccionadosIds.filter((id) => id !== atb_id);
      delete this.varPropiedadPorAtributo[atb_id];
    }
  }

  get atributosParaFormularioVariacion(): AtributoVariacion[] {
    if (this.atributosFijos.length > 0) {
      return this.atributosFijos;
    }
    return this.atributosVariaciones.filter((a) => this.atributosSeleccionadosIds.includes(a.atb_id));
  }

  saveVariacion(): void {
    const atributosActivos = this.atributosParaFormularioVariacion;

    if (atributosActivos.length === 0) {
      this.toastr.error('Selecciona al menos un atributo para la variación', 'Validación');
      return;
    }

    const propiedadIds: number[] = [];
    for (const atributo of atributosActivos) {
      const propId = this.varPropiedadPorAtributo[atributo.atb_id];
      if (!propId) {
        this.toastr.error(`Selecciona un valor para "${atributo.atb_nombre}"`, 'Validación');
        return;
      }
      propiedadIds.push(+propId);
    }

    if (this.varPrecioAdicional < 0) {
      this.toastr.error('El precio adicional debe ser mayor o igual a 0', 'Validación');
      return;
    }
    if (this.varStock < 0 || this.varStockMinimo < 0) {
      this.toastr.error('El stock no puede ser negativo', 'Validación');
      return;
    }

    const data = {
      producto_id: this.productId,
      propiedad_ids: propiedadIds,
      precio_adicional: this.varPrecioAdicional,
      stock: this.varStock,
      stock_minimo: this.varStockMinimo,
      estado: this.varEstado,
    };

    this.service.createVariacion(data).subscribe((resp) => {
      if (resp.message === 403) {
        this.toastr.error(resp.message_text, 'Validación');
        return;
      }
      this.toastr.success('Variación registrada correctamente', 'Éxito');
      this.variaciones.unshift(resp.variacion);
      this.actualizarAtributosFijos();
      this.varPropiedadPorAtributo = {};
      this.varPrecioAdicional = 0;
      this.varStock = 0;
      this.varStockMinimo = 0;
      this.varEstado = true;
      this.atributosSeleccionadosIds = [];
    });
  }

  // ==================== VARIACIONES: EDITAR ====================

  openEditVariacion(item: Variacion): void {
    this.editVar = item;
    this.editVarPrecioAdicional = item.var_preadi;
    this.editVarStock = item.var_stock;
    this.editVarStockMinimo = item.var_stkmin;
    this.editVarEstado = !!item.var_estado;

    this.editVarPropiedadPorAtributo = {};
    for (const p of item.propiedades) {
      this.editVarPropiedadPorAtributo[p.atb_id] = String(p.prp_id);
    }

    this.editVarVisible = true;
  }

  get atributosParaEdicionVariacion(): AtributoVariacion[] {
    if (!this.editVar) return [];
    const atbIds = [...new Set(this.editVar.propiedades.map((p) => p.atb_id))];
    return this.atributosVariaciones.filter((a) => atbIds.includes(a.atb_id));
  }

  saveEditVariacion(): void {
    if (!this.editVar) return;

    const atributosActivos = this.atributosParaEdicionVariacion;
    const propiedadIds: number[] = [];
    for (const atributo of atributosActivos) {
      const propId = this.editVarPropiedadPorAtributo[atributo.atb_id];
      if (!propId) {
        this.toastr.error(`Selecciona un valor para "${atributo.atb_nombre}"`, 'Validación');
        return;
      }
      propiedadIds.push(+propId);
    }

    const data = {
      producto_id: this.productId,
      propiedad_ids: propiedadIds,
      precio_adicional: this.editVarPrecioAdicional,
      stock: this.editVarStock,
      stock_minimo: this.editVarStockMinimo,
      estado: this.editVarEstado,
    };

    this.service.updateVariacion(this.editVar.var_proid, data).subscribe((resp) => {
      if (resp.message === 403) {
        this.toastr.error(resp.message_text, 'Validación');
        return;
      }
      this.toastr.success('Variación actualizada correctamente', 'Éxito');
      const index = this.variaciones.findIndex((v) => v.var_proid === resp.variacion.var_proid);
      if (index !== -1) this.variaciones[index] = resp.variacion;
      this.editVarVisible = false;
    });
  }

  openDeleteVariacion(item: Variacion): void {
    this.deleteVar = item;
    this.deleteVarVisible = true;
  }

  confirmDeleteVariacion(): void {
    if (!this.deleteVar) return;
    this.service.deleteVariacion(this.deleteVar.var_proid).subscribe(() => {
      this.variaciones = this.variaciones.filter((v) => v.var_proid !== this.deleteVar!.var_proid);
      this.actualizarAtributosFijos();
      this.toastr.success('Variación eliminada', 'Éxito');
      this.deleteVarVisible = false;
    });
  }
}
