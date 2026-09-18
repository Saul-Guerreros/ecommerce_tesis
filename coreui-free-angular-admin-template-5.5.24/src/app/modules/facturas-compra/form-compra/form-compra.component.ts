import {
  Component, DestroyRef, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges, inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule, FormArray, FormBuilder, FormGroup, Validators } from '@angular/forms';
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

/** Fecha local YYYY-MM-DD (toISOString() usa UTC y en Paraguay adelanta el día después de las 21:00). */
function fechaLocal(d = new Date()): string {
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

/** 'YYYY-MM-DD' -> 'DD/MM/YYYY' */
function fmt(fecha?: string | null): string {
  if (!fecha) return '';
  const [y, m, d] = fecha.substring(0, 10).split('-');
  return `${d}/${m}/${y}`;
}

/**
 * Modal único con dos modos: Nuevo (editable) y Ver (solo lectura).
 * Una compra confirmada no se edita: solo se anula.
 *
 * Flujo de cabecera:
 *   Proveedor → Tipo comprobante → Timbrado (filtrado) → Vto. (auto)
 *   → N° factura (est/punto del timbrado) → Fecha emisión (≤ hoy y dentro de vigencia)
 *   Sucursal → Depósito (filtrado por sucursal)
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

  form: FormGroup;
  timbradoForm: FormGroup;
  guardando = false;
  readonly hoy = fechaLocal();
  readonly fmt = fmt;

  proveedores: ProveedorOption[] = [];
  sucursales: SucursalOption[] = [];
  depositos: DepositoOption[] = [];
  tiposComprobante: TipoComprobanteOption[] = [];
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
  nuevoDescuento: number | null = null;
  private equivalenciasSub?: Subscription;

  constructor(
    private fb: FormBuilder,
    private service: ComprasService,
    private equivalenciasService: EquivalenciasService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      com_proid:  [null, [Validators.required]],
      com_tcoid:  [null, [Validators.required]],
      com_tpvid:  [null],                               // required dinámico según tco_reqtim
      com_nrodoc: ['', [Validators.required, Validators.pattern(/^\d{3}-\d{3}-\d{7}$/)]],
      com_fchaem: ['', [Validators.required]],          // SIN valor por defecto: es la fecha impresa en la factura
      com_nrorem: [''],
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
      tpv_numero:   ['', [Validators.required, Validators.pattern(/^\d{1,8}$/)]],
      tpv_fechin:   ['', [Validators.required]],
      tpv_fechfn:   ['', [Validators.required]],
    });
  }

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (res) => {
        this.proveedores = res.proveedores;
        this.sucursales = res.sucursales ?? [];
        this.depositos = res.depositos;
        this.tiposComprobante = res.tipos_comprobante;
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

  /**
   * distinctUntilChanged evita que reset()/disable() (que re-emiten el
   * mismo valor) limpien campos dependientes por error.
   */
  private escucharCambiosCabecera(): void {
    const proveedor$ = this.form.get('com_proid')!.valueChanges.pipe(distinctUntilChanged());
    const tipo$ = this.form.get('com_tcoid')!.valueChanges.pipe(distinctUntilChanged());
    const sucursal$ = this.form.get('com_sucid')!.valueChanges.pipe(distinctUntilChanged());

    // Proveedor o tipo cambian → el timbrado anterior deja de ser válido.
    proveedor$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => this.recargarTimbrados());
    tipo$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.actualizarValidadorTimbrado();
      this.recargarTimbrados();
    });

    // Sucursal cambia → se limpia el depósito y se preselecciona el principal.
    sucursal$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((sucId) => {
      const deps = this.depositos.filter((d) => d.dpo_sucid === sucId);
      const sugerido = deps.find((d) => d.dpo_princi === 1) ?? (deps.length === 1 ? deps[0] : null);
      this.form.get('com_depid')!.setValue(sugerido?.dpo_id ?? null);
    });
  }

  private actualizarValidadorTimbrado(): void {
    const ctrl = this.form.get('com_tpvid')!;
    ctrl.setValidators(this.requiereTimbrado ? [Validators.required] : []);
    ctrl.updateValueAndValidity({ emitEvent: false });
  }

  private recargarTimbrados(): void {
    this.timbradosSub?.unsubscribe();
    this.form.get('com_tpvid')!.setValue(null);
    this.timbrados = [];
    this.mostrarNuevoTimbrado = false;

    // El select se habilita solo cuando hay proveedor + tipo que requiere timbrado.
    const ctrl = this.form.get('com_tpvid')!;
    const { com_proid, com_tcoid } = this.form.getRawValue();
    const habilitar = !!com_proid && !!com_tcoid && this.requiereTimbrado;
    habilitar ? ctrl.enable({ emitEvent: false }) : ctrl.disable({ emitEvent: false });
    if (!habilitar) return;

    this.cargandoTimbrados = true;
    this.timbradosSub = this.service.getTimbradosProveedor(com_proid, com_tcoid).subscribe({
      next: (res) => {
        this.timbrados = res;
        this.cargandoTimbrados = false;
        // Si hay uno solo que cubre hoy, se sugiere automáticamente.
        const vigentes = res.filter((t) => t.tpv_fechin <= this.hoy && t.tpv_fechfn >= this.hoy);
        if (vigentes.length === 1) this.form.get('com_tpvid')!.setValue(vigentes[0].tpv_id);
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

  get tipoSeleccionado(): TipoComprobanteOption | undefined {
    return this.tiposComprobante.find((t) => t.tco_id === this.form.get('com_tcoid')!.value);
  }

  get requiereTimbrado(): boolean {
    return Number(this.tipoSeleccionado?.tco_reqtim ?? 1) === 1;
  }

  get timbradoSeleccionado(): TimbradoProveedorOption | undefined {
    return this.timbrados.find((t) => t.tpv_id === this.form.get('com_tpvid')!.value);
  }

  /** Vto. del timbrado: SOLO LECTURA, sale del timbrado elegido (o del snapshot en modo Ver). */
  get vtoTimbrado(): string {
    if (this.soloLectura) return fmt(this.compra?.com_timbvn);
    return fmt(this.timbradoSeleccionado?.tpv_fechfn);
  }

  timbradoVencidoHoy(t: TimbradoProveedorOption): boolean {
    return t.tpv_fechfn < this.hoy;
  }

  // ==================== Validaciones cruzadas ====================

  /** Fecha de emisión: no futura y dentro de la vigencia del timbrado (NO se compara el vto. con hoy). */
  get errorFecha(): string | null {
    const fecha: string = this.form.get('com_fchaem')!.value;
    if (!fecha) return null;
    if (fecha > this.hoy) return 'La fecha de emisión no puede ser posterior a hoy.';

    const t = this.timbradoSeleccionado;
    if (t && (fecha < t.tpv_fechin || fecha > t.tpv_fechfn)) {
      return `Fuera de la vigencia del timbrado (${fmt(t.tpv_fechin)} al ${fmt(t.tpv_fechfn)}).`;
    }
    return null;
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
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
      // emitEvent:false → no disparar la cascada (limpiar depósito/timbrado).
      this.form.reset({
        com_proid:  this.compra.com_proid,
        com_tcoid:  this.compra.com_tcoid,
        com_tpvid:  this.compra.com_tpvid ?? null,
        com_nrodoc: this.compra.com_nrodoc,
        com_fchaem: this.compra.com_fchaem,
        com_nrorem: this.compra.com_nrorem ?? '',
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
      (this.compra.detalle ?? []).forEach((linea: any) => this.detalle.push(this.filaDetalle({
        prd_id:     linea.cmd_prdid,
        prd_codinv: linea.prd_codinv ?? '',
        umd_id:     linea.cmd_umdid,
        umd_codigo: linea.umd_codigo ?? '',
        descri:     linea.cmd_descri,
        precun:     linea.cmd_precun,
        despor:     linea.cmd_despor,
        tasa:       linea.cmd_tasa,
        canfac:     linea.cmd_canfac,
        canrec:     linea.cmd_canrec,
        tiv_id:     linea.cmd_tivid,
        gravada:    linea.cmd_gravada,
        iva:        linea.cmd_iva,
      }), { emitEvent: false }));

      this.form.disable({ emitEvent: false });
    } else {
      this.form.reset({ com_condic: 0 }, { emitEvent: false });
      this.detalle.clear({ emitEvent: false });
      this.form.enable({ emitEvent: false });
      this.form.get('com_tpvid')!.disable({ emitEvent: false }); // hasta elegir proveedor + tipo
      this.actualizarValidadorTimbrado();
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
      tasa:       [linea.tasa ?? 0],
      canfac:     [linea.canfac ?? 0],
      canrec:     [linea.canrec ?? linea.canfac ?? 0],
      tiv_id:     [linea.tiv_id ?? null],
      gravada:    [linea.gravada ?? 0],
      iva:        [linea.iva ?? 0],
    });
  }

  // ==================== Alta rápida de timbrado del proveedor ====================

  abrirNuevoTimbrado(): void {
    this.timbradoForm.reset({ tpv_numero: '', tpv_fechin: '', tpv_fechfn: '' });
    this.mostrarNuevoTimbrado = true;
  }

  guardarNuevoTimbrado(): void {
    const { com_proid, com_tcoid } = this.form.getRawValue();
    if (!com_proid || !com_tcoid) {
      this.toastr.error('Seleccione primero proveedor y tipo de comprobante', 'Falta dato');
      return;
    }
    if (this.timbradoForm.invalid) {
      this.timbradoForm.markAllAsTouched();
      return;
    }
    const v = this.timbradoForm.value;
    if (v.tpv_fechfn < v.tpv_fechin) {
      this.toastr.error('El vencimiento no puede ser anterior al inicio de vigencia', 'Fechas inválidas');
      return;
    }

    this.guardandoTimbrado = true;
    this.service.crearTimbradoProveedor({ ...v, tpv_proid: com_proid, tpv_tcoid: com_tcoid }).subscribe({
      next: (t) => {
        this.guardandoTimbrado = false;
        this.timbrados = [t, ...this.timbrados];
        this.form.get('com_tpvid')!.setValue(t.tpv_id);
        this.mostrarNuevoTimbrado = false;
        this.toastr.success('Timbrado registrado', 'Éxito');
      },
      error: (err) => {
        this.guardandoTimbrado = false;
        const errores = err?.error?.errors;
        const msg = errores ? (Object.values(errores)[0] as string[])[0] : err?.error?.message;
        this.toastr.error(msg ?? 'No se pudo registrar el timbrado', 'Error');
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

  /**
   * FIX del buscador: si el usuario edita/borra el texto después de haber
   * elegido un producto, la selección anterior se descarta. Antes quedaba
   * "pegada" y el *ngIf="!productoSeleccionado" ocultaba la lista para siempre.
   */
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
    this.equivalenciasSub?.unsubscribe();   // cancela equivalencias de un producto anterior

    this.productoSeleccionado = p;
    this.busquedaProducto = this.etiquetaProducto(p);

    const base: UnidadCompraOption = {
      umd_id: p.prd_umdid, umd_codigo: p.umd_codigo ?? 'Unidad', umd_nombre: p.umd_codigo ?? 'Unidad', factor: 1,
    };
    this.unidadesDisponibles = [base];
    this.unidadSeleccionada = base;

    this.equivalenciasSub = this.equivalenciasService.getByProducto(p.prd_id).subscribe({
      next: (eqs: ProductoEquivalencia[]) => {
        if (this.productoSeleccionado?.prd_id !== p.prd_id) return; // respuesta tardía de otro producto
        const extras = eqs.filter((e) => e.peq_estado === 1).map((e) => ({
          umd_id: e.peq_umdid, umd_codigo: e.umd_codigo ?? '', umd_nombre: e.umd_nombre ?? '', factor: e.peq_factor,
        }));
        this.unidadesDisponibles = [base, ...extras]; // nueva referencia → el <select> se refresca
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

  agregarItem(): void {
    if (!this.productoSeleccionado) { this.toastr.error('Busque y seleccione un producto', 'Falta producto'); return; }
    if (!this.unidadSeleccionada)   { this.toastr.error('Seleccione la unidad de compra', 'Falta unidad'); return; }
    if (!this.nuevoCosto || this.nuevoCosto <= 0) { this.toastr.error('El precio debe ser mayor a 0', 'Precio inválido'); return; }
    if (!this.nuevaCantidad || this.nuevaCantidad <= 0) { this.toastr.error('La cantidad debe ser mayor a 0', 'Cantidad inválida'); return; }
    const desc = this.nuevoDescuento ?? 0;
    if (desc < 0 || desc > 100) { this.toastr.error('El descuento debe estar entre 0 y 100', 'Descuento inválido'); return; }

    const p = this.productoSeleccionado;
    this.detalle.push(this.filaDetalle({
      prd_id: p.prd_id,
      prd_codinv: p.prd_codinv,
      umd_id: this.unidadSeleccionada.umd_id,
      umd_codigo: this.unidadSeleccionada.umd_codigo,
      descri: p.prd_descri,
      precun: this.nuevoCosto,
      despor: desc,
      tasa: p.tiv_tasa ?? 0,
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
    this.descartarProducto();
    this.busquedaProducto = '';
    this.nuevoCosto = null;
    this.nuevaCantidad = null;
    this.nuevoDescuento = null;
  }

  // ==================== Totales ====================

  gravadoLinea(fila: any): number {
    const bruto = (Number(fila.precun) || 0) * (Number(fila.canfac) || 0);
    return bruto - bruto * ((Number(fila.despor) || 0) / 100);
  }

  private sumaPorTasa(tasa: number): number {
    return this.detalle.controls
      .filter((c) => Number(c.value.tasa) === tasa)
      .reduce((s, c) => s + this.gravadoLinea(c.value), 0);
  }

  get totalExenta(): number { return this.sumaPorTasa(0); }
  get totalGrav5(): number  { return this.sumaPorTasa(5); }
  get totalGrav10(): number { return this.sumaPorTasa(10); }
  get totalIva5(): number   { return this.totalGrav5 * 0.05; }
  get totalIva10(): number  { return this.totalGrav10 * 0.10; }
  get totalGeneral(): number {
    return this.totalExenta + this.totalGrav5 + this.totalGrav10 + this.totalIva5 + this.totalIva10;
  }

  ivaTotalVista(c: Compra | null): number {
    return (Number(c?.com_iva5) || 0) + (Number(c?.com_iva10) || 0);
  }

  // ==================== Guardar ====================

  guardar(): void {
    if (this.detalle.length === 0) {
      this.toastr.error('Debe cargar al menos un ítem antes de guardar', 'Detalle vacío');
      return;
    }

    if (this.form.invalid || this.errorFecha) {
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

    const payload = {
      ...this.form.value,   // .value excluye plazo/cuotas/intervalo deshabilitados (contado)
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
