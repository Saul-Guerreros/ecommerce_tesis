import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule, FormArray, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import {
  Compra, CompraDetalle, ProveedorOption, DepositoOption,
  TipoComprobanteOption, ProductoBusqueda,
} from '../models/compra.model';
import { ComprasService } from '../service/compras.service';

/**
 * Modal unico con dos modos: Nuevo (formulario editable, sin com_id) y Ver
 * (solo lectura). No existe modo "Editar" sobre una compra ya confirmada:
 * el backend no tiene update() a proposito (ver nota en el chat) — una
 * compra confirmada solo se anula, nunca se modifica.
 */
@Component({
  selector: 'app-form-compra',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule],
  templateUrl: './form-compra.component.html',
})
export class FormCompraComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() compra: Compra | null = null;      // null = modo Nuevo
  @Input() soloLectura = false;                // true = modo Ver

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();
  @Output() anular = new EventEmitter<Compra>();

  form: FormGroup;
  guardando = false;

  proveedores: ProveedorOption[] = [];
  depositos: DepositoOption[] = [];
  tiposComprobante: TipoComprobanteOption[] = [];
  productosDisponibles: ProductoBusqueda[] = [];

  // --- toolbar de alta de item ---
  busquedaProducto = '';
  productoSeleccionado: ProductoBusqueda | null = null;
  nuevoCosto: number | null = null;
  nuevaCantidad: number | null = null;

  constructor(
    private fb: FormBuilder,
    private service: ComprasService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      com_proid:  [null, [Validators.required]],
      com_depid:  [null, [Validators.required]],
      com_tcoid:  [null, [Validators.required]],
      com_timbra: ['', [Validators.maxLength(8)]],
      com_timbvn: [''],
      com_nrodoc: ['', [Validators.required, Validators.pattern(/^\d{3}-\d{3}-\d{7}$/)]],
      com_fchaem: ['', [Validators.required]],
      com_nrorem: [''],
      com_condic: [0],
      com_plzdia: [0],
      com_cuotas: [0],
      com_interv: [0],
      com_fchvto: [''],
      com_obs:    [''],
      detalle:    this.fb.array([]),
    });
  }

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (res) => {
        this.proveedores = res.proveedores;
        this.depositos = res.depositos;
        this.tiposComprobante = res.tipos_comprobante;
      },
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
    return !this.compra?.com_id;
  }

  get sucursalDerivada(): string {
    const dep = this.depositos.find((d) => d.dpo_id === this.form.value.com_depid);
    return dep?.suc_nombre ?? '';
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

      if (this.compra) {
        // Ver una compra existente: se puebla el form pero queda deshabilitado.
        this.form.reset({
          com_proid:  this.compra.com_proid,
          com_depid:  this.compra.com_depid,
          com_tcoid:  this.compra.com_tcoid,
          com_timbra: this.compra.com_timbra ?? '',
          com_timbvn: this.compra.com_timbvn ?? '',
          com_nrodoc: this.compra.com_nrodoc,
          com_fchaem: this.compra.com_fchaem,
          com_nrorem: this.compra.com_nrorem ?? '',
          com_condic: this.compra.com_condic,
          com_plzdia: this.compra.com_plzdia ?? 0,
          com_cuotas: this.compra.com_cuotas ?? 0,
          com_interv: this.compra.com_interv ?? 0,
          com_fchvto: this.compra.com_fchvto ?? '',
          com_obs:    this.compra.com_obs ?? '',
        });

        this.detalle.clear();
        // El backend devuelve los campos con el prefijo real de la tabla
        // (cmd_prdid, cmd_descri, cmd_costo...) — se traducen aca a los
        // nombres simples que usa el FormArray internamente (los mismos
        // que arma agregarItem() al cargar un item nuevo desde la busqueda).
        (this.compra.detalle ?? []).forEach((linea: any) => this.detalle.push(this.filaDetalle({
          prd_id:     linea.cmd_prdid,
          prd_codinv: linea.prd_codinv ?? '',
          descri:     linea.cmd_descri,
          costo:      linea.cmd_costo,
          tasa:       linea.cmd_tasa,
          canfac:     linea.cmd_canfac,
          canrec:     linea.cmd_canrec,
          tiv_id:     linea.cmd_tivid,
        })));

        this.form.disable();
      } else {
        // Nueva compra: form limpio y habilitado.
        this.form.reset({ com_condic: 0, com_plzdia: 0, com_cuotas: 0, com_interv: 0 });
        this.detalle.clear();
        this.form.enable();
        this.onCondicionChange(0); // arranca en Contado: plazo/cuotas/interv deshabilitados
      }
    }
  }

  private filaDetalle(linea: Partial<CompraDetalle> = {}): FormGroup {
    return this.fb.group({
      prd_id:     [linea.prd_id ?? null],
      prd_codinv: [linea.prd_codinv ?? ''],
      descri:     [linea.descri ?? ''],
      costo:      [linea.costo ?? 0],
      tasa:       [linea.tasa ?? 0],
      canfac:     [linea.canfac ?? 0],
      canrec:     [linea.canrec ?? linea.canfac ?? 0],
      tiv_id:     [linea.tiv_id ?? null],
    });
  }

  // ==================== Toolbar: agregar item ====================

  seleccionarProducto(p: ProductoBusqueda): void {
    this.productoSeleccionado = p;
    this.busquedaProducto = `${p.prd_codinv} — ${p.prd_descri}`;
    this.nuevoCosto = p.prd_prevta;
  }

  agregarItem(): void {
    if (!this.productoSeleccionado) {
      this.toastr.error('Busque y seleccione un producto', 'Falta producto');
      return;
    }
    if (!this.nuevoCosto || this.nuevoCosto <= 0) {
      this.toastr.error('El precio debe ser mayor a 0', 'Precio inválido');
      return;
    }
    if (!this.nuevaCantidad || this.nuevaCantidad <= 0) {
      this.toastr.error('La cantidad debe ser mayor a 0', 'Cantidad inválida');
      return;
    }

    const p = this.productoSeleccionado;
    const tasa = p.tiv_tasa ?? 0;

    this.detalle.push(this.filaDetalle({
      prd_id: p.prd_id,
      prd_codinv: p.prd_codinv,
      descri: p.prd_descri,
      costo: this.nuevoCosto,
      tasa,
      canfac: this.nuevaCantidad,
      canrec: this.nuevaCantidad,
      tiv_id: p.prd_tivid ?? null,
    }));

    this.limpiarToolbarItem();
  }

  quitarItem(i: number): void {
    this.detalle.removeAt(i);
  }

  private limpiarToolbarItem(): void {
    this.busquedaProducto = '';
    this.productoSeleccionado = null;
    this.nuevoCosto = null;
    this.nuevaCantidad = null;
  }

  // ==================== Subtotales reactivos ====================

  private gravadoLinea(fila: any): number {
    return (Number(fila.costo) || 0) * (Number(fila.canfac) || 0);
  }

  get totalExenta(): number {
    return this.detalle.controls
      .filter((c) => Number(c.value.tasa) === 0)
      .reduce((s, c) => s + this.gravadoLinea(c.value), 0);
  }

  get totalGrav5(): number {
    return this.detalle.controls
      .filter((c) => Number(c.value.tasa) === 5)
      .reduce((s, c) => s + this.gravadoLinea(c.value), 0);
  }

  get totalGrav10(): number {
    return this.detalle.controls
      .filter((c) => Number(c.value.tasa) === 10)
      .reduce((s, c) => s + this.gravadoLinea(c.value), 0);
  }

  get totalIva5(): number {
    return this.totalGrav5 * 0.05;
  }

  get totalIva10(): number {
    return this.totalGrav10 * 0.10;
  }

  get totalGeneral(): number {
    return this.totalExenta + this.totalGrav5 + this.totalGrav10 + this.totalIva5 + this.totalIva10;
  }

  // ==================== Guardar ====================

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

    if (this.form.value.com_condic === 1 && (!this.form.value.com_cuotas || this.form.value.com_cuotas < 1)) {
      this.toastr.error('Indique la cantidad de cuotas para compras a crédito', 'Falta dato');
      return;
    }

    const depositoElegido = this.depositos.find((d) => d.dpo_id === this.form.value.com_depid);

    if (!depositoElegido) {
      this.toastr.error('Seleccione un depósito válido', 'Falta dato');
      return;
    }

    this.guardando = true;

    const payload = {
      ...this.form.value,
      com_sucid: depositoElegido.dpo_sucid,
      com_exenta: this.totalExenta,
      com_grav5:  this.totalGrav5,
      com_grav10: this.totalGrav10,
      com_iva5:   this.totalIva5,
      com_iva10:  this.totalIva10,
      com_total:  this.totalGeneral,
      detalle: this.detalle.value.map((l: any) => ({
        prd_id: l.prd_id,
        tiv_id: l.tiv_id,
        descri: l.descri,
        costo: l.costo,
        tasa: l.tasa,
        gravada: this.gravadoLinea(l),
        iva: this.gravadoLinea(l) * (Number(l.tasa) / 100),
        canfac: l.canfac,
        canrec: l.canrec,
      })),
    };

    this.service.create(payload).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Compra registrada', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            // Los errores de detalle.N.campo no tienen FormControl propio:
            // se muestran todos juntos por toast para no complicar el
            // mapeo campo-por-campo dentro del FormArray.
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la compra', 'Error');
      },
    });
  }

  solicitarAnular(): void {
    if (this.compra) {
      this.anular.emit(this.compra);
    }
  }

  onCondicionChange(valor: number): void {
    this.form.patchValue({ com_condic: valor });

    const campos = ['com_plzdia', 'com_cuotas', 'com_interv'];

    if (valor === 0) {
      // Contado: se deshabilitan A NIVEL DE FORMCONTROL (no solo visual) y
      // se resetean a 0, para que nunca viajen valores viejos en el payload.
      campos.forEach((c) => {
        this.form.get(c)?.setValue(0);
        this.form.get(c)?.disable();
      });
    } else {
      campos.forEach((c) => this.form.get(c)?.enable());
    }
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}