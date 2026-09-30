import {
  Component, DestroyRef, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges, inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { CommonModule } from '@angular/common';
import {
  AbstractControl, FormArray, FormBuilder, FormGroup, FormsModule, ReactiveFormsModule,
  ValidationErrors, Validators,
} from '@angular/forms';
import { Subscription } from 'rxjs';
import { distinctUntilChanged } from 'rxjs/operators';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import {
  Compra, CompraDetalle, ProveedorOption, DepositoOption, SucursalOption,
  TipoComprobanteOption, ProductoBusqueda, UnidadCompraOption, TimbradoProveedorOption,
} from '../models/compra.model';
import { ComprasService } from '../service/compras.service';
import { EquivalenciasService } from '../../productos/service/equivalencias.service';
import { ProductoEquivalencia } from '../../productos/models/producto.model';

/** Antigüedad máxima de la fecha de emisión (días). */
const DIAS_MAX_ANTIGUEDAD = 10;
/** 001-001-0000000 → 13 dígitos. */
const DIGITOS_NRO_FACTURA = 13;

type CampoTotal = 'com_exenta' | 'com_grav5' | 'com_grav10' | 'com_iva5' | 'com_iva10' | 'com_total';

/** Fecha local YYYY-MM-DD (toISOString() usa UTC y en Paraguay adelanta el día después de las 21:00). */
function fechaLocal(d = new Date()): string {
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

function sumarDias(fecha: string, dias: number): string {
  const [y, m, d] = fecha.split('-').map(Number);
  return fechaLocal(new Date(y, m - 1, d + dias));
}

/** 'YYYY-MM-DD' -> 'DD/MM/YYYY' */
function fmt(fecha?: string | null): string {
  if (!fecha) return '';
  const [y, m, d] = fecha.substring(0, 10).split('-');
  return `${d}/${m}/${y}`;
}

/** Deja solo dígitos (máx. 13) y arma 001-001-0000000 mientras se escribe o se pega. */
function formatearNroFactura(valor: string): string {
  const d = (valor ?? '').replace(/\D/g, '').slice(0, DIGITOS_NRO_FACTURA);
  if (d.length <= 3) return d;
  if (d.length <= 6) return `${d.slice(0, 3)}-${d.slice(3)}`;
  return `${d.slice(0, 3)}-${d.slice(3, 6)}-${d.slice(6)}`;
}

/** IVA contenido en un monto con IVA incluido: 10% = ÷11, 5% = ÷21. */
function ivaIncluido(monto: number, tasa: number): number {
  return tasa > 0 ? Math.round((monto * tasa) / (100 + tasa)) : 0;
}

/**
 * Modal único con dos modos: Nuevo (editable) y Ver (solo lectura).
 * Tipo de comprobante fijo: Factura.
 * Flujo: Proveedor → Timbrado (vto. auto) → N° factura → Fecha emisión.
 */
@Component({
  selector: 'app-form-compra',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule],
  templateUrl: './form-compra.component.html',
})
export class FormCompraComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() compra: Compra | null = null;
  @Input() soloLectura = false;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();
  @Output() anular = new EventEmitter<Compra>();

  private destroyRef = inject(DestroyRef);

  readonly hoy = fechaLocal();
  readonly fechaMinima = sumarDias(this.hoy, -DIAS_MAX_ANTIGUEDAD);
  readonly digitosRequeridos = DIGITOS_NRO_FACTURA;
  readonly fmt = fmt;

  form: FormGroup;
  timbradoForm: FormGroup;
  guardando = false;

  proveedores: ProveedorOption[] = [];
  sucursales: SucursalOption[] = [];
  depositos: DepositoOption[] = [];
  tipoFactura: TipoComprobanteOption | null = null;
  productosDisponibles: ProductoBusqueda[] = [];

  // --- timbrados del proveedor ---
  timbrados: TimbradoProveedorOption[] = [];
  cargandoTimbrados = false;
  mostrarNuevoTimbrado = false;
  guardandoTimbrado = false;
  private timbradosSub?: Subscription;

  // --- toolbar de alta de ítem ---
  busquedaProducto = '';
  productoSeleccionado: ProductoBusqueda | null = null;
  unidadesDisponibles: UnidadCompraOption[] = [];
  unidadSeleccionada: UnidadCompraOption | null = null;
  nuevoCosto: number | null = null;
  nuevaCantidad: number | null = null;
  nuevoDescPor: number | null = null;     // ayuda de carga
  nuevoDescMonto: number | null = null;   // fuente de verdad (Gs. por unidad)
  private equivalenciasSub?: Subscription;

  // ==================== Validadores (tiempo real) ====================

  /** No futura y con antigüedad máxima de DIAS_MAX_ANTIGUEDAD. */
  private readonly fechaEmisionValidator = (c: AbstractControl): ValidationErrors | null => {
    const f: string = c.value;
    if (!f) return null;
    if (f > this.hoy) return { futura: true };
    if (f < this.fechaMinima) return { antigua: true };
    return null;
  };

  /** El timbrado debe cubrir la fecha de emisión (o hoy, si todavía no se cargó). */
  private readonly timbradoVigenteValidator = (c: AbstractControl): ValidationErrors | null => {
    const t = this.timbrados.find((x) => x.tpv_id === c.value);
    if (!t) return null;
    const referencia: string = this.form?.get('com_fchaem')?.value || this.hoy;
    return t.tpv_fechfn < referencia ? { vencido: t.tpv_fechfn } : null;
  };

  /** Un timbrado nuevo que venció antes de la fecha mínima no sirve para ninguna factura cargable. */
  private readonly vencimientoUtilValidator = (c: AbstractControl): ValidationErrors | null =>
    c.value && c.value < this.fechaMinima ? { inutil: true } : null;

  constructor(
    private fb: FormBuilder,
    private service: ComprasService,
    private equivalenciasService: EquivalenciasService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      com_proid:  [null, [Validators.required]],
      com_tcoid:  [null, [Validators.required]],   // fijo: Factura (lo asigna la config)
      com_tpvid:  [null, [Validators.required, this.timbradoVigenteValidator]],
      com_nrodoc: ['', [Validators.required, Validators.pattern(/^\d{3}-\d{3}-\d{7}$/)]],
      com_fchaem: ['', [Validators.required, this.fechaEmisionValidator]],

      com_sucid:  [null, [Validators.required]],
      com_depid:  [null, [Validators.required]],
      com_condic: [0],
      com_plzdia: [null],
      com_cuotas: [null],
      com_interv: [null],
      com_fchvto: [''],
      com_obs:    [''],
      detalle:    this.fb.array([]),
    });

    this.timbradoForm = this.fb.group({
      tpv_numero: ['', [Validators.required, Validators.pattern(/^\d{1,8}$/)]],
      tpv_fechfn: ['', [Validators.required, this.vencimientoUtilValidator]],
    });
  }

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (res) => {
        this.proveedores = res.proveedores;
        this.sucursales = res.sucursales ?? [];
        this.depositos = res.depositos;
        this.tipoFactura = res.tipo_factura ?? null;

        if (!this.tipoFactura) {
          this.toastr.error('No está configurado el tipo de comprobante "Factura"', 'Configuración');
          return;
        }
        if (!this.soloLectura) {
          this.form.get('com_tcoid')!.setValue(this.tipoFactura.tco_id, { emitEvent: false });
        }
      },
      error: () => this.toastr.error('No se pudo cargar la configuración del formulario', 'Error'),
    });

    this.service.buscarProductos().subscribe({
      next: (res) => (this.productosDisponibles = res),
      error: () => {},
    });

    this.escucharCambiosCabecera();
  }

  // ==================== Dependencias entre campos ====================

  private escucharCambiosCabecera(): void {
    const ctrl = (n: string) => this.form.get(n)!;

    // Proveedor cambia → el timbrado anterior deja de ser válido.
    ctrl('com_proid').valueChanges
      .pipe(distinctUntilChanged(), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.recargarTimbrados());

    // Fecha de emisión cambia → se revalida el timbrado en el acto.
    ctrl('com_fchaem').valueChanges
      .pipe(distinctUntilChanged(), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.revalidarTimbrado());

    // Al elegir (o autoseleccionar) un timbrado, su error se muestra de inmediato.
    ctrl('com_tpvid').valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((id) => { if (id) ctrl('com_tpvid').markAsTouched(); });

    // Sucursal cambia → se limpia el depósito y se preselecciona el principal.
    ctrl('com_sucid').valueChanges
      .pipe(distinctUntilChanged(), takeUntilDestroyed(this.destroyRef))
      .subscribe((sucId) => {
        const deps = this.depositos.filter((d) => d.dpo_sucid === sucId);
        const sugerido = deps.find((d) => d.dpo_princi === 1) ?? (deps.length === 1 ? deps[0] : null);
        ctrl('com_depid').setValue(sugerido?.dpo_id ?? null);
      });
  }

  private revalidarTimbrado(): void {
    const c = this.form.get('com_tpvid')!;
    if (c.disabled || !c.value) return;
    c.updateValueAndValidity({ emitEvent: false });
    c.markAsTouched();
  }

  private recargarTimbrados(): void {
    this.timbradosSub?.unsubscribe();
    const ctrl = this.form.get('com_tpvid')!;
    ctrl.setValue(null, { emitEvent: false });
    this.timbrados = [];
    this.mostrarNuevoTimbrado = false;

    const { com_proid, com_tcoid } = this.form.getRawValue();
    if (!com_proid || !com_tcoid) {
      ctrl.disable({ emitEvent: false });
      return;
    }
    ctrl.enable({ emitEvent: false });

    this.cargandoTimbrados = true;
    this.timbradosSub = this.service.getTimbradosProveedor(com_proid, com_tcoid).subscribe({
      next: (res) => {
        this.timbrados = res;
        this.cargandoTimbrados = false;
        // Solo se autoselecciona si hay UN único vigente (varias cajas = elige el usuario).
        const vigentes = res.filter((t) => t.tpv_fechfn >= this.hoy);
        if (vigentes.length === 1) ctrl.setValue(vigentes[0].tpv_id);
      },
      error: () => {
        this.cargandoTimbrados = false;
        this.toastr.error('No se pudieron cargar los timbrados del proveedor', 'Error');
      },
    });
  }

  // ==================== Getters de apoyo ====================

  get detalle(): FormArray {
    return this.form.get('detalle') as FormArray;
  }

  get esNuevo(): boolean {
    return !this.compra?.com_id;
  }

  get depositosFiltrados(): DepositoOption[] {
    const sucId = this.form.get('com_sucid')!.value;
    return sucId ? this.depositos.filter((d) => d.dpo_sucid === sucId) : [];
  }

  get timbradoSeleccionado(): TimbradoProveedorOption | undefined {
    return this.timbrados.find((t) => t.tpv_id === this.form.get('com_tpvid')!.value);
  }

  get vtoTimbrado(): string {
    if (this.soloLectura) return fmt(this.compra?.com_timbvn);
    return fmt(this.timbradoSeleccionado?.tpv_fechfn);
  }

  get placeholderTimbrado(): string {
    if (!this.form.get('com_proid')!.value) return 'Primero elija el proveedor';
    if (this.cargandoTimbrados) return 'Cargando...';
    return this.timbrados.length ? 'Seleccione un timbrado' : 'Sin timbrados: registre uno con +';
  }

  etiquetaTimbrado(t: TimbradoProveedorOption): string {
    return t.tpv_numero;
  }

  /** Vencido antes de la fecha mínima permitida: no puede cubrir ninguna factura. */
  timbradoInutilizable(t: TimbradoProveedorOption): boolean {
    return t.tpv_fechfn < this.fechaMinima;
  }

  get todosVencidos(): boolean {
    return this.timbrados.length > 0 && this.timbrados.every((t) => t.tpv_fechfn < this.hoy);
  }

  // ==================== Mensajes de error (tiempo real) ====================

  private erroresVisibles(campo: string): ValidationErrors | null {
    const c = this.form.get(campo);
    return c && c.invalid && (c.touched || c.dirty) ? c.errors : null;
  }

  campoInvalido(campo: string): boolean {
    return !!this.erroresVisibles(campo);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  get digitosNroFactura(): number {
    return String(this.form.get('com_nrodoc')!.value ?? '').replace(/\D/g, '').length;
  }

  get errorNroFactura(): string | null {
    const e = this.erroresVisibles('com_nrodoc');
    if (!e) return null;
    if (e['backend']) return e['backend'];
    if (e['required']) return 'El número de factura es obligatorio.';
    return `Faltan ${DIGITOS_NRO_FACTURA - this.digitosNroFactura} dígito(s). Formato: 001-001-0000000.`;
  }

  get errorFechaEmision(): string | null {
    const e = this.erroresVisibles('com_fchaem');
    if (!e) return null;
    if (e['backend']) return e['backend'];
    if (e['required']) return 'La fecha de emisión es obligatoria.';
    if (e['futura']) return 'La fecha de emisión no puede ser posterior a hoy.';
    if (e['antigua']) return `No puede tener más de ${DIAS_MAX_ANTIGUEDAD} días de antigüedad (mínimo ${fmt(this.fechaMinima)}).`;
    return null;
  }

  get errorTimbrado(): string | null {
    const e = this.erroresVisibles('com_tpvid');
    if (!e) return null;
    if (e['backend']) return e['backend'];
    if (e['vencido']) {
      const ref = this.form.get('com_fchaem')!.value ? 'antes de la fecha de emisión' : 'antes de hoy';
      return `Timbrado vencido el ${fmt(e['vencido'])} (${ref}). Registre el timbrado nuevo con el botón +.`;
    }
    if (e['required']) return 'Seleccione el timbrado impreso en la factura.';
    return null;
  }

  campoTimbradoInvalido(campo: string): boolean {
    const c = this.timbradoForm.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  /** Máscara: solo dígitos, máximo 13, guiones automáticos (también al pegar). */
  onNroFacturaInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const formateado = formatearNroFactura(input.value);
    input.value = formateado;
    this.form.get('com_nrodoc')!.setValue(formateado);
  }

  // ==================== Ciclo de apertura del modal ====================

  ngOnChanges(changes: SimpleChanges): void {
    if (!(changes['visible'] && this.visible)) return;

    this.guardando = false;
    this.limpiarToolbarItem();
    this.timbrados = [];
    this.mostrarNuevoTimbrado = false;
    this.timbradoForm.reset();

    if (this.compra) {
      this.form.reset({
        com_proid:  this.compra.com_proid,
        com_tcoid:  this.compra.com_tcoid,
        com_tpvid:  this.compra.com_tpvid ?? null,
        com_nrodoc: this.compra.com_nrodoc,
        com_fchaem: this.compra.com_fchaem,
        
        com_sucid:  this.compra.com_sucid ?? null,
        com_depid:  this.compra.com_depid,
        com_condic: this.compra.com_condic,
        com_plzdia: this.compra.com_plzdia ?? null,
        com_cuotas: this.compra.com_cuotas ?? null,
        com_interv: this.compra.com_interv ?? null,
        com_fchvto: this.compra.com_fchvto ?? '',
        com_obs:    this.compra.com_obs ?? '',
      }, { emitEvent: false });

      this.detalle.clear({ emitEvent: false });
      (this.compra.detalle ?? []).forEach((linea: any) => {
        // cmd_canfac/canrec se guardan en unidad de STOCK: se vuelven a la unidad facturada.
        const factor = Number(linea.cmd_factconv) || 1;
        this.detalle.push(this.filaDetalle({
          prd_id:     linea.cmd_prdid,
          prd_codinv: linea.prd_codinv ?? '',
          // (e) al cargar una compra existente (bloque con linea.cmd_factconv):
          umd_id:     linea.cmd_umdid,
          peq_id:     linea.cmd_peqid ?? null,
          umd_codigo: factor !== 1 ? `${linea.umd_codigo ?? ''} (x${factor})` : (linea.umd_codigo ?? ''),
          descri:     linea.cmd_descri,
          precun:     Number(linea.cmd_precun),
          despor:     Number(linea.cmd_despor),
          descto:     Number(linea.cmd_descto),
          tasa:       Number(linea.cmd_tasa),
          canfac:     Number(linea.cmd_canfac) / factor,
          canrec:     Number(linea.cmd_canrec) / factor,
          tiv_id:     linea.cmd_tivid,
          gravada:    Number(linea.cmd_gravada),
          iva:        Number(linea.cmd_iva),
        }), { emitEvent: false });
      });

      this.form.disable({ emitEvent: false });
    } else {
      this.form.reset({ com_condic: 0, com_tcoid: this.tipoFactura?.tco_id ?? null }, { emitEvent: false });
      this.detalle.clear({ emitEvent: false });
      this.form.enable({ emitEvent: false });
      this.form.get('com_tpvid')!.disable({ emitEvent: false }); // hasta elegir proveedor
      this.onCondicionChange(0);
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
      descto:     [linea.descto ?? 0],
      tasa:       [linea.tasa ?? 0],
      canfac:     [linea.canfac ?? 0],
      canrec:     [linea.canrec ?? linea.canfac ?? 0],
      tiv_id:     [linea.tiv_id ?? null],
      gravada:    [linea.gravada ?? 0],
      iva:        [linea.iva ?? 0],
      // (c) filaDetalle(): agrega el control
      peq_id:     [linea.peq_id ?? null],
      
    });
  }

  // ==================== Alta rápida de timbrado del proveedor ====================

  abrirNuevoTimbrado(): void {
    this.timbradoForm.reset({ tpv_numero: '', tpv_fechfn: '' });
    this.mostrarNuevoTimbrado = true;
  }

  guardarNuevoTimbrado(): void {
    const { com_proid, com_tcoid } = this.form.getRawValue();
    if (!com_proid || !com_tcoid) {
      this.toastr.error('Seleccione primero el proveedor', 'Falta dato');
      return;
    }
    if (this.timbradoForm.invalid) {
      this.timbradoForm.markAllAsTouched();
      return;
    }

    this.guardandoTimbrado = true;
    this.service.crearTimbradoProveedor({ ...this.timbradoForm.value, tpv_proid: com_proid, tpv_tcoid: com_tcoid }).subscribe({
      next: (t) => {
        this.guardandoTimbrado = false;
        this.timbrados = [t, ...this.timbrados];
        this.form.get('com_tpvid')!.setValue(t.tpv_id);
        this.mostrarNuevoTimbrado = false;
        this.toastr.success('Timbrado registrado', 'Éxito');
      },
      error: (err) => {
        this.guardandoTimbrado = false;
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            const c = this.timbradoForm.get(campo);
            c?.setErrors({ backend: err.error.errors[campo][0] });
            c?.markAsTouched();
          });
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar el timbrado', 'Error');
      },
    });
  }

  // ==================== Toolbar: búsqueda y alta de ítem ====================

  get productosFiltrados(): ProductoBusqueda[] {
    const q = this.busquedaProducto.trim().toLowerCase();
    if (!q || this.productoSeleccionado) return [];
    return this.productosDisponibles
      .filter((p) => p.prd_codinv.toLowerCase().includes(q) || p.prd_descri.toLowerCase().includes(q))
      .slice(0, 8);
  }

  private etiquetaProducto(p: ProductoBusqueda): string {
    return `${p.prd_codinv} — ${p.prd_descri}`;
  }

  onBusquedaChange(texto: string): void {
    this.busquedaProducto = texto ?? '';
    if (this.productoSeleccionado && this.busquedaProducto !== this.etiquetaProducto(this.productoSeleccionado)) {
      this.descartarProducto();
    }
  }

  private descartarProducto(): void {
    this.equivalenciasSub?.unsubscribe();
    this.productoSeleccionado = null;
    this.unidadesDisponibles = [];
    this.unidadSeleccionada = null;
  }

  seleccionarProducto(p: ProductoBusqueda): void {
    this.equivalenciasSub?.unsubscribe();
    this.productoSeleccionado = p;
    this.busquedaProducto = this.etiquetaProducto(p);

   // (a) seleccionarProducto(): reemplaza la construcción de `base` y de `extras`
  const base: UnidadCompraOption = {
    umd_id: p.prd_umdid, peq_id: null,
    umd_codigo: p.umd_codigo ?? 'Unidad', umd_nombre: p.umd_codigo ?? 'Unidad',
    factor: 1, etiqueta: p.umd_codigo ?? 'Unidad',
  };
  this.unidadesDisponibles = [base];
  this.unidadSeleccionada = base;

  this.equivalenciasSub = this.equivalenciasService.getByProducto(p.prd_id).subscribe({
    next: (eqs: ProductoEquivalencia[]) => {
      if (this.productoSeleccionado?.prd_id !== p.prd_id) return;
      const extras: UnidadCompraOption[] = eqs
        .filter((e) => e.peq_estado === 1)                 // solo presentaciones activas
        .map((e) => ({
          umd_id: e.peq_umdid, peq_id: e.peq_id ?? null,
          umd_codigo: e.umd_codigo ?? '', umd_nombre: e.umd_nombre ?? '',
          factor: Number(e.peq_factor),
          etiqueta: e.etiqueta ?? `${e.umd_codigo} (x${Number(e.peq_factor)})`,
        }));
      this.unidadesDisponibles = [base, ...extras];
    },
    error: () => {},
  });
  }

  seleccionarUnidad(u: UnidadCompraOption): void {
    this.unidadSeleccionada = u;
  }

  get cantidadConvertida(): number {
    return (this.nuevaCantidad ?? 0) * (this.unidadSeleccionada?.factor ?? 1);
  }

  // --- Descuento bidireccional: se edita % o monto; se persiste el MONTO ---

  private porcentajeDesde(monto: number | null, precio: number | null): number | null {
    if (monto === null || !precio) return null;
    return Math.round((monto / precio) * 10000) / 100;   // 2 decimales
  }

  onPrecioChange(valor: number | null): void {
    this.nuevoCosto = valor;
    // El monto es la fuente de verdad: si cambia el precio, se recalcula el %.
    if (this.nuevoDescMonto !== null) this.nuevoDescPor = this.porcentajeDesde(this.nuevoDescMonto, valor);
  }

  onDescPorChange(valor: number | null): void {
    this.nuevoDescPor = valor;
    this.nuevoDescMonto = valor === null || !this.nuevoCosto
      ? null
      : Math.round((this.nuevoCosto * valor) / 100);   // Gs. enteros
  }

  onDescMontoChange(valor: number | null): void {
    this.nuevoDescMonto = valor;
    this.nuevoDescPor = this.porcentajeDesde(valor, this.nuevoCosto);
  }

  get errorPrecio(): string | null {
    if (this.nuevoCosto === null) return null;
    return this.nuevoCosto > 0 ? null : 'El precio debe ser mayor a 0.';
  }

  get errorCantidad(): string | null {
    if (this.nuevaCantidad === null) return null;
    return this.nuevaCantidad > 0 ? null : 'La cantidad debe ser mayor a 0.';
  }

  get errorDescuento(): string | null {
    const monto = this.nuevoDescMonto ?? 0;
    const por = this.nuevoDescPor ?? 0;
    if (monto < 0 || por < 0) return 'El descuento no puede ser negativo.';
    if (por > 100) return 'El % no puede superar 100.';
    if (this.nuevoCosto && monto > this.nuevoCosto) return 'El descuento no puede superar el precio unitario.';
    return null;
  }

  get itemValido(): boolean {
    return !!this.productoSeleccionado && !!this.unidadSeleccionada
      && (this.nuevoCosto ?? 0) > 0 && (this.nuevaCantidad ?? 0) > 0 && !this.errorDescuento;
  }

  agregarItem(): void {
    if (!this.itemValido) return;   // el botón ya está deshabilitado; es una red de seguridad

    const p = this.productoSeleccionado!;
    this.detalle.push(this.filaDetalle({
      prd_id: p.prd_id,
      prd_codinv: p.prd_codinv,
      // (b) agregarItem(): en this.filaDetalle({...})
      umd_id: this.unidadSeleccionada!.umd_id,
      peq_id: this.unidadSeleccionada!.peq_id,
      umd_codigo: this.unidadSeleccionada!.etiqueta,     // la grilla muestra "Caja (x10)"
      descri: p.prd_descri,
      precun: this.nuevoCosto,
      despor: this.nuevoDescPor ?? 0,
      descto: this.nuevoDescMonto ?? 0,
      tasa: p.tiv_tasa ?? 0,
      canfac: this.nuevaCantidad,
      canrec: this.nuevaCantidad,
      tiv_id: p.prd_tivid ?? null,
      
    }));


      if (!p.prd_tivid) {
        this.toastr.error(
        'Este producto no tiene tipo de IVA configurado. Edítelo en Productos antes de comprarlo.',
        'Falta tipo de IVA'
      );
      return;
    }

    this.limpiarToolbarItem();
  }

  quitarItem(i: number): void {
    this.detalle.removeAt(i);
  }

  private limpiarToolbarItem(): void {
    this.descartarProducto();
    this.busquedaProducto = '';
    this.nuevoCosto = null;
    this.nuevaCantidad = null;
    this.nuevoDescPor = null;
    this.nuevoDescMonto = null;
  }

  // ==================== Totales (IVA incluido) ====================

  /** Total de la operación: (precio − descuento unitario) × cantidad. */
  netoLinea(f: any): number {
    const neto = ((Number(f.precun) || 0) - (Number(f.descto) || 0)) * (Number(f.canfac) || 0);
    return Math.round(neto);
  }

  /** Monto de la línea en su columna (Exentas / 5% / 10%); null en las otras. */
  montoEnColumna(f: any, tasa: number): number | null {
    return Number(f.tasa) === tasa ? this.netoLinea(f) : null;
  }

  private sumaPorTasa(tasa: number): number {
    return this.detalle.controls
      .filter((c) => Number(c.value.tasa) === tasa)
      .reduce((s, c) => s + this.netoLinea(c.value), 0);
  }

  get subtotalExentas(): number { return this.sumaPorTasa(0); }
  get subtotal5(): number       { return this.sumaPorTasa(5); }
  get subtotal10(): number      { return this.sumaPorTasa(10); }

  get totalDescuento(): number {
    return this.detalle.controls.reduce(
      (s, c) => s + (Number(c.value.descto) || 0) * (Number(c.value.canfac) || 0), 0,
    );
  }

  get totalAPagar(): number { return this.subtotalExentas + this.subtotal5 + this.subtotal10; }
  get iva5(): number        { return ivaIncluido(this.subtotal5, 5); }
  get iva10(): number       { return ivaIncluido(this.subtotal10, 10); }

  /** En modo Ver se muestran los importes persistidos (los calculó el backend). */
  valorVista(campo: CampoTotal, calculado: number): number {
    return this.soloLectura ? Number(this.compra?.[campo]) || 0 : calculado;
  }

  get totalIvaVista(): number {
    return this.valorVista('com_iva5', this.iva5) + this.valorVista('com_iva10', this.iva10);
  }

  // ==================== Guardar ====================

  guardar(): void {
    if (this.detalle.length === 0) {
      this.toastr.error('Debe cargar al menos un ítem antes de guardar', 'Detalle vacío');
      return;
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toastr.error('Revise los campos marcados en rojo', 'Validación');
      return;
    }

    const v = this.form.getRawValue();
    if (v.com_condic === 1 && (!v.com_cuotas || v.com_cuotas < 1)) {
      this.toastr.error('Indique la cantidad de cuotas para compras a crédito', 'Falta dato');
      return;
    }

    this.guardando = true;

    // Los totales NO se envían: el backend los recalcula desde el detalle.
    const payload = {
      ...this.form.value,
      detalle: this.detalle.value.map((l: any) => ({
        prd_id: l.prd_id,
       // (d) payload del guardado: agrega peq_id al map del detalle
        umd_id: l.umd_id,
        peq_id: l.peq_id,
        tiv_id: l.tiv_id,
        descri: l.descri,
        precun: l.precun,
        descto: l.descto,
        despor: l.despor,
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
            const control = this.form.get(campo.split('.')[0]);
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
    if (this.compra) this.anular.emit(this.compra);
  }

  onCondicionChange(valor: number): void {
    this.form.patchValue({ com_condic: valor });
    const campos = ['com_plzdia', 'com_cuotas', 'com_interv'];
    if (valor === 0) {
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
}