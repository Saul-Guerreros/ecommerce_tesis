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

// nuevos imports
import { EquivalenciasService } from '../../productos/service/equivalencias.service';
import { ProductoEquivalencia } from '../../productos/models/producto.model';
import { UnidadCompraOption } from '../models/compra.model';

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

  // nueva propiedad de la clase
  unidadesDisponibles: UnidadCompraOption[] = [];
  unidadSeleccionada: UnidadCompraOption | null = null;

  nuevoDescuento: number | null = null;


constructor(
  private fb: FormBuilder,
  private service: ComprasService,
  private equivalenciasService: EquivalenciasService,   // <- nuevo
  private toastr: ToastrService
  
) {
  this.form = this.fb.group({
    com_proid:  [null, [Validators.required]],
    com_depid:  [null, [Validators.required]],
    com_tcoid:  [null, [Validators.required]],
    com_timbra: ['', [Validators.maxLength(8)]],     // <-- ESTA LÍNEA
    com_timbvn: [''],
    com_nrodoc: ['', [Validators.required, Validators.pattern(/^\d{3}-\d{3}-\d{7}$/)]],
    com_fchaem: ['', [Validators.required]],
    com_nrorem: [''],
    com_condic: [0],
    com_plzdia: [null],
    com_cuotas: [null],
    com_interv: [null],
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
          com_plzdia: this.compra.com_plzdia ?? null,
          com_cuotas: this.compra.com_cuotas ?? null,
          com_interv: this.compra.com_interv ?? null,
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
          umd_id:     linea.cmd_umdid,
          umd_codigo: linea.umd_codigo ?? '',
          descri:     linea.cmd_descri,
          precun:     linea.cmd_precun,
          tasa:       linea.cmd_tasa,
          canfac:     linea.cmd_canfac,
          canrec:     linea.cmd_canrec,
          tiv_id:     linea.cmd_tivid,
          gravada:    linea.cmd_gravada,   // NUEVO
          iva:        linea.cmd_iva,        // NUEVO
        })));

        this.form.disable();
      } else {
        // Nueva compra: form limpio y habilitado.
        this.form.reset({ com_condic: 0, com_plzdia: null, com_cuotas: null, com_interv: null });
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
      umd_id:     [linea.umd_id ?? null],
      umd_codigo: [linea.umd_codigo ?? ''],
      descri:     [linea.descri ?? ''],
      precun:     [linea.precun ?? 0],
      despor:     [linea.despor ?? 0],
      tasa:       [linea.tasa ?? 0],
      canfac:     [linea.canfac ?? 0],
      canrec:     [linea.canrec ?? linea.canfac ?? 0],
      tiv_id:     [linea.tiv_id ?? null],
      gravada:    [linea.gravada ?? 0],
      iva:        [linea.iva ?? 0],
    });
  }

  // ==================== Toolbar: agregar item ====================

  seleccionarProducto(p: ProductoBusqueda): void {
  this.productoSeleccionado = p;
  this.busquedaProducto = `${p.prd_codinv} — ${p.prd_descri}`;
  this.nuevoCosto = null; // (podés renombrar esto a nuevoPrecun si preferís consistencia total)

  this.unidadesDisponibles = [{ umd_id: p.prd_umdid, umd_codigo: p.umd_codigo ?? 'Unidad', umd_nombre: p.umd_codigo ?? 'Unidad', factor: 1 }];
  this.unidadSeleccionada = this.unidadesDisponibles[0];

  this.equivalenciasService.getByProducto(p.prd_id).subscribe({
    next: (eqs: ProductoEquivalencia[]) => {
      eqs.filter(e => e.peq_estado === 1).forEach(e => {
        this.unidadesDisponibles.push({
          umd_id: e.peq_umdid,
          umd_codigo: e.umd_codigo ?? '',
          umd_nombre: e.umd_nombre ?? '',
          factor: e.peq_factor,
        });
      });
    },
    error: () => {}, // sin equivalencias no es un error, el producto solo se compra en su unidad base
  });
}

seleccionarUnidad(u: UnidadCompraOption): void {
  this.unidadSeleccionada = u;
}

// Ayuda visual en el toolbar: "2 Caja x 12 = 24 unidades ingresarán a stock"
get cantidadConvertida(): number {
  const factor = this.unidadSeleccionada?.factor ?? 1;
  return (this.nuevaCantidad ?? 0) * factor;
}

  agregarItem(): void {
  if (!this.productoSeleccionado) { this.toastr.error('Busque y seleccione un producto', 'Falta producto'); return; }
  if (!this.unidadSeleccionada)   { this.toastr.error('Seleccione la unidad de compra', 'Falta unidad'); return; }
  if (!this.nuevoCosto || this.nuevoCosto <= 0) { this.toastr.error('El precio debe ser mayor a 0', 'Precio inválido'); return; }
  if (!this.nuevaCantidad || this.nuevaCantidad <= 0) { this.toastr.error('La cantidad debe ser mayor a 0', 'Cantidad inválida'); return; }

  const p = this.productoSeleccionado;
  const tasa = p.tiv_tasa ?? 0;

  this.detalle.push(this.filaDetalle({
    prd_id: p.prd_id,
    prd_codinv: p.prd_codinv,
    umd_id: this.unidadSeleccionada.umd_id,
    umd_codigo: this.unidadSeleccionada.umd_codigo,
    descri: p.prd_descri,
    precun: this.nuevoCosto,
    despor: this.nuevoDescuento ?? 0,
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
    this.nuevoDescuento = null;
  }

  // ==================== Subtotales reactivos ====================
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

  // 5) gravadoLinea(): ahora publico y neto de descuento
  gravadoLinea(fila: any): number {
    const bruto = (Number(fila.precun) || 0) * (Number(fila.canfac) || 0);
    const descuento = bruto * ((Number(fila.despor) || 0) / 100);
    return bruto - descuento;
  }

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
        umd_id: l.umd_id,
        tiv_id: l.tiv_id,
        descri: l.descri,
        precun: l.precun,
        despor: l.despor,
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
        this.form.get(c)?.setValue(null);
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

  ivaTotalVista(c: Compra | null): number {
  return (Number(c?.com_iva5) || 0) + (Number(c?.com_iva10) || 0);
}
}