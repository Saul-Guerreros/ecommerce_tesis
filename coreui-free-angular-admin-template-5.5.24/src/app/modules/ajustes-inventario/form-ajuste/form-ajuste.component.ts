import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule, FormArray, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule, BadgeModule } from '@coreui/angular';
import { AjusteInventario, AjusteDetalle, TipoAjusteOption, DepositoOption } from '../models/ajuste-inventario.model';
import { ProductoBusqueda } from '../../facturas-compra/models/compra.model';
import { AjustesInventarioService } from '../service/ajustes-inventario.service';

/**
 * Modal unico: modo Nuevo (crea un Borrador, editable) y modo Ver (solo
 * lectura, para cualquier estado). No hay modo Editar: un Borrador ya
 * cargado no se modifica, se elimina y se vuelve a cargar si esta mal.
 */
@Component({
  selector: 'app-form-ajuste',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule, BadgeModule],
  templateUrl: './form-ajuste.component.html',
})
export class FormAjusteComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() ajuste: AjusteInventario | null = null;   // null = modo Nuevo
  @Input() soloLectura = false;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();
  @Output() confirmarAjuste = new EventEmitter<AjusteInventario>();
  @Output() anularAjuste = new EventEmitter<AjusteInventario>();
  @Output() eliminarBorrador = new EventEmitter<AjusteInventario>();

  form: FormGroup;
  guardando = false;

  tipos: TipoAjusteOption[] = [];
  depositos: DepositoOption[] = [];
  productosDisponibles: ProductoBusqueda[] = [];

  busquedaProducto = '';
  productoSeleccionado: ProductoBusqueda | null = null;
  nuevaCantfis: number | null = null;
  nuevoCosto: number | null = null;

  constructor(
    private fb: FormBuilder,
    private service: AjustesInventarioService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      aji_tpaid:      [null, [Validators.required]],
      aji_depid:      [null, [Validators.required]],
      aji_fecha:      ['', [Validators.required]],
      aji_referencia: [''],
      aji_obs:        [''],
      detalle:        this.fb.array([]),
    });
  }

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (res) => { this.tipos = res.tipos; this.depositos = res.depositos; },
      error: () => this.toastr.error('No se pudo cargar la configuración del formulario', 'Error'),
    });

    this.service.buscarProductos().subscribe({
      next: (res) => (this.productosDisponibles = res),
      error: () => {},
    });
  }

  get detalle(): FormArray {
    return this.form.get('detalle') as FormArray;
  }

  get esNuevo(): boolean {
    return !this.ajuste?.aji_id;
  }

  get tipoSeleccionado(): TipoAjusteOption | undefined {
    return this.tipos.find((t) => t.tpa_id === this.form.value.aji_tpaid);
  }

  get requiereReferencia(): boolean {
    return this.tipoSeleccionado?.tpa_reqcom === 1;
  }

  get productosFiltrados(): ProductoBusqueda[] {
    if (!this.busquedaProducto.trim()) return [];
    const q = this.busquedaProducto.toLowerCase();
    return this.productosDisponibles
      .filter((p) => p.prd_codinv.toLowerCase().includes(q) || p.prd_descri.toLowerCase().includes(q))
      .slice(0, 8);
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.guardando = false;
      this.limpiarToolbarItem();

      if (this.ajuste) {
        this.form.reset({
          aji_tpaid:      this.ajuste.aji_tpaid,
          aji_depid:      this.ajuste.aji_depid,
          aji_fecha:      this.ajuste.aji_fecha,
          aji_referencia: this.ajuste.aji_referencia ?? '',
          aji_obs:        this.ajuste.aji_obs ?? '',
        });

        this.detalle.clear();
        (this.ajuste.detalle ?? []).forEach((linea: any) => this.detalle.push(this.filaDetalle({
          prd_id:     linea.aid_prdid,
          prd_codinv: linea.prd_codinv,
          prd_descri: linea.prd_descri,
          cantsis:    linea.aid_cantsis,
          cantfis:    linea.aid_cantfis,
          costo:      linea.aid_costo,
          obs:        linea.aid_obs,
        })));

        this.form.disable();
      } else {
        this.form.reset();
        this.detalle.clear();
        this.form.enable();
      }
    }
  }

  private filaDetalle(linea: Partial<AjusteDetalle> = {}): FormGroup {
    return this.fb.group({
      prd_id:     [linea.prd_id ?? null],
      prd_codinv: [linea.prd_codinv ?? ''],
      prd_descri: [linea.prd_descri ?? ''],
      cantsis:    [linea.cantsis ?? null],
      cantfis:    [linea.cantfis ?? 0],
      costo:      [linea.costo ?? null],
      obs:        [linea.obs ?? ''],
    });
  }

  // ==================== Toolbar: agregar item ====================

  seleccionarProducto(p: ProductoBusqueda): void {
    this.productoSeleccionado = p;
    this.busquedaProducto = `${p.prd_codinv} — ${p.prd_descri}`;
  }

  agregarItem(): void {
    if (!this.productoSeleccionado) {
      this.toastr.error('Busque y seleccione un producto', 'Falta producto');
      return;
    }
    if (this.nuevaCantfis === null || this.nuevaCantfis < 0) {
      this.toastr.error('Indique la cantidad física contada (puede ser 0)', 'Cantidad inválida');
      return;
    }

    const p = this.productoSeleccionado;

    this.detalle.push(this.filaDetalle({
      prd_id: p.prd_id,
      prd_codinv: p.prd_codinv,
      prd_descri: p.prd_descri,
      cantfis: this.nuevaCantfis,
      costo: this.nuevoCosto,
    }));

    this.limpiarToolbarItem();
  }

  quitarItem(i: number): void {
    this.detalle.removeAt(i);
  }

  private limpiarToolbarItem(): void {
    this.busquedaProducto = '';
    this.productoSeleccionado = null;
    this.nuevaCantfis = null;
    this.nuevoCosto = null;
  }

  // ==================== Guardar (crea Borrador) ====================

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.detalle.length === 0) {
      this.toastr.error('Debe cargar al menos un ítem antes de guardar', 'Detalle vacío');
      return;
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    if (this.requiereReferencia && !this.form.value.aji_referencia) {
      this.toastr.error('El tipo de ajuste seleccionado exige indicar una referencia', 'Falta dato');
      return;
    }

    this.guardando = true;

    const payload = {
      ...this.form.value,
      detalle: this.detalle.value.map((l: any) => ({
        prd_id: l.prd_id,
        cantfis: l.cantfis,
        costo: l.costo,
        obs: l.obs || null,
      })),
    };

    this.service.create(payload).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Ajuste guardado como borrador', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            const base = campo.split('.')[0];
            const control = this.form.get(base);
            if (control) {
              control.setErrors({ backend: err.error.errors[campo][0] });
              control.markAsTouched();
            }
          });
          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el ajuste', 'Error');
      },
    });
  }

  solicitarConfirmar(): void {
    if (this.ajuste) this.confirmarAjuste.emit(this.ajuste);
  }

  solicitarAnular(): void {
    if (this.ajuste) this.anularAjuste.emit(this.ajuste);
  }

  solicitarEliminar(): void {
    if (this.ajuste) this.eliminarBorrador.emit(this.ajuste);
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}