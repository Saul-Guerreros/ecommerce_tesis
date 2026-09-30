import { Component, DestroyRef, EventEmitter, Input, Output, OnChanges, SimpleChanges, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { CommonModule } from '@angular/common';
import {
  AbstractControl, FormArray, FormBuilder, FormGroup, ReactiveFormsModule,
  ValidationErrors, Validators,
} from '@angular/forms';
import { Subscription } from 'rxjs';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Compra, TimbradoProveedorOption } from '../../models/compra.model';
import { NotaCreditoConfig, LineaNCConfig, TipoComprobanteNC, MotivoNotaCredito }
  from '../models/nota-credito.model';

import { NotasCreditoService } from '../service/notas-credito.service';
import { ComprasService } from '../../service/compras.service';

/** 000-000-0000000 → 13 dígitos. */
const DIGITOS_NUMERO = 13;

/** Fecha local YYYY-MM-DD (evita el corrimiento de día por UTC). */
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

/** Deja solo dígitos (máx. 13) y arma 000-000-0000000 mientras se escribe o se pega. */
function formatearNumero(valor: string): string {
  const d = (valor ?? '').replace(/\D/g, '').slice(0, DIGITOS_NUMERO);
  if (d.length <= 3) return d;
  if (d.length <= 6) return `${d.slice(0, 3)}-${d.slice(3)}`;
  return `${d.slice(0, 3)}-${d.slice(3, 6)}-${d.slice(6)}`;
}

/**
 * Nota de Crédito de una compra: transcribe un documento que YA EXISTE del
 * proveedor. Flujo: Timbrado (propio, tipo Nota de Crédito) → N° NC → Fecha
 * (no anterior a la factura). Proveedor y RUC vienen de la factura, sin
 * duplicar dato. Por línea: cantidad + Descuento Unitario (Gs., fuente de
 * verdad) o % (ayuda de carga) — bidireccional.
 */
@Component({
  selector: 'app-form-nota-credito',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule],
  templateUrl: './form-nota-credito.component.html',
})
export class FormNotaCreditoComponent implements OnChanges {
  @Input() visible = false;
  @Input() compra: Compra | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  private destroyRef = inject(DestroyRef);

  readonly hoy = fechaLocal();
  readonly digitosRequeridos = DIGITOS_NUMERO;
  readonly fmt = fmt;

  form: FormGroup;
  timbradoForm: FormGroup;
  guardando = false;
  cargandoConfig = false;

  lineasDisponibles: LineaNCConfig[] = [];
  proveedorId: number | null = null;
  proveedorRazon = '';
  proveedorRuc = '';
  fechaMinima = this.hoy;              // se actualiza con com_fchaem al cargar config
  tipoNotaCredito: TipoComprobanteNC | null = null;
  motivos: MotivoNotaCredito[] = [];

  // --- timbrados del proveedor (tipo Nota de Crédito) ---
  timbrados: TimbradoProveedorOption[] = [];
  cargandoTimbrados = false;
  mostrarNuevoTimbrado = false;
  guardandoTimbrado = false;
  private timbradosSub?: Subscription;

  // ==================== Validadores (tiempo real) ====================

  private readonly fechaNCValidator = (c: AbstractControl): ValidationErrors | null => {
    const f: string = c.value;
    if (!f) return null;
    if (f > this.hoy) return { futura: true };
    if (f < this.fechaMinima) return { anterior: true };
    return null;
  };

  private readonly timbradoVigenteValidator = (c: AbstractControl): ValidationErrors | null => {
    const t = this.timbrados.find((x) => x.tpv_id === c.value);
    if (!t) return null;
    const referencia: string = this.form?.get('ncr_fecha')?.value || this.fechaMinima;
    return t.tpv_fechfn < referencia ? { vencido: t.tpv_fechfn } : null;
  };

  private readonly vencimientoUtilValidator = (c: AbstractControl): ValidationErrors | null =>
    c.value && c.value < this.fechaMinima ? { inutil: true } : null;

  constructor(
    private fb: FormBuilder,
    private service: NotasCreditoService,
    private comprasService: ComprasService,
    private toastr: ToastrService,
  ) {
        this.form = this.fb.group({
      ncr_tpvid:  [null, [Validators.required, this.timbradoVigenteValidator]],
      ncr_numero: ['', [Validators.required, Validators.pattern(/^\d{3}-\d{3}-\d{7}$/)]],
      ncr_fecha:  ['', [Validators.required, this.fechaNCValidator]],
      ncr_mncid:  [null, [Validators.required]],
      ncr_obs:    [''],
      detalle:    this.fb.array([]),
    });

    this.timbradoForm = this.fb.group({
      tpv_numero: ['', [Validators.required, Validators.pattern(/^\d{1,8}$/)]],
      tpv_fechfn: ['', [Validators.required, this.vencimientoUtilValidator]],
    });
  }

  get detalle(): FormArray {
    return this.form.get('detalle') as FormArray;
  }

  // ==================== Ciclo de apertura del modal ====================

  ngOnChanges(changes: SimpleChanges): void {
    if (!(changes['visible'] && this.visible && this.compra?.com_id)) return;

    this.guardando = false;
    this.timbrados = [];
    this.mostrarNuevoTimbrado = false;
    this.timbradoForm.reset();
    this.form.reset({ ncr_tpvid: null, ncr_numero: '', ncr_fecha: '', ncr_mncid: null, ncr_obs: '' });
    this.form.get('ncr_tpvid')!.disable({ emitEvent: false }); // hasta que llegue la config
    this.detalle.clear();

    this.cargarConfig();
  }

  private cargarConfig(): void {
    this.cargandoConfig = true;
    this.service.getConfig(this.compra!.com_id!).subscribe({
      next: (res: NotaCreditoConfig) => {
        this.proveedorId = res.com_proid;
        this.proveedorRazon = res.com_proraz;
        this.proveedorRuc = res.com_proruc;
        this.fechaMinima = res.com_fchaem;
        this.tipoNotaCredito = res.tipo_nota_credito;
        this.motivos = res.motivos ?? [];

        this.lineasDisponibles = res.lineas.filter((l) => l.saldo_acreditable > 0);
        this.lineasDisponibles.forEach((l) => this.detalle.push(this.filaDetalle(l)));

        if (!this.tipoNotaCredito) {
          this.toastr.error('No está configurado el tipo de comprobante "Nota de Crédito"', 'Configuración');
        } else {
          this.recargarTimbrados();
        }

        this.cargandoConfig = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar el detalle de la compra', 'Error');
        this.cargandoConfig = false;
      },
    });
  }

  private recargarTimbrados(): void {
    if (!this.proveedorId || !this.tipoNotaCredito) return;

    this.form.get('ncr_tpvid')!.enable({ emitEvent: false });
    this.cargandoTimbrados = true;

    this.timbradosSub = this.comprasService.getTimbradosProveedor(this.proveedorId, this.tipoNotaCredito.tco_id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.timbrados = res;
          this.cargandoTimbrados = false;
          const vigentes = res.filter((t) => t.tpv_fechfn >= this.hoy);
          if (vigentes.length === 1) this.form.get('ncr_tpvid')!.setValue(vigentes[0].tpv_id);
        },
        error: () => {
          this.cargandoTimbrados = false;
          this.toastr.error('No se pudieron cargar los timbrados del proveedor', 'Error');
        },
      });
  }

  private filaDetalle(l: LineaNCConfig): FormGroup {
    return this.fb.group({
      cmd_id:              [l.cmd_id],
      incluir:             [false],
      cmd_descri:          [l.cmd_descri],
      cmd_tasa:            [l.cmd_tasa],
      cmd_precun:          [l.cmd_precun],
      precio_neto:         [l.precio_neto],
      saldo_acreditable:   [l.saldo_acreditable],
      stock_disponible:    [l.stock_disponible],
      cantidad:            [null as number | null, [Validators.min(0)]],
      despor:              [null as number | null, [Validators.min(0), Validators.max(100)]],
      descuento_unitario:  [null as number | null, [Validators.min(0)]],
      importe:             [0],   // derivado: importe unitario x cantidad (IVA incl.)
    });
  }

  // ==================== Descuento bidireccional (por línea) ====================
    private porcentajeDesde(importeUnit: number | null, precio: number | null): number | null {
    if (importeUnit === null || !precio) return null;
    return Math.round((importeUnit / precio) * 10000) / 100;
  }

  private recalcularImporte(fila: AbstractControl): void {
    const cantidad = Number(fila.get('cantidad')?.value) || 0;
    const unitario = Number(fila.get('descuento_unitario')?.value) || 0;
    fila.get('importe')?.setValue(Math.round(unitario * cantidad));   // Gs. sin decimales
  }

  onCantidadChange(fila: AbstractControl): void {
    this.recalcularImporte(fila);
  }

  /** Se edita el %: el importe unitario se recalcula (ayuda de carga). */
  onDesporChange(fila: AbstractControl): void {
    const precio = Number(fila.get('precio_neto')?.value) || 0;
    const despor = fila.get('despor')?.value;
    const unitario = despor === null || despor === '' ? null : Math.round((precio * Number(despor)) / 100);
    fila.get('descuento_unitario')?.setValue(unitario, { emitEvent: false });
    this.recalcularImporte(fila);
  }

  /** Se edita el importe unitario (fuente de verdad): el % es solo visual. */
  onDescuentoUnitChange(fila: AbstractControl): void {
    const precio = Number(fila.get('precio_neto')?.value) || 0;
    const valor = fila.get('descuento_unitario')?.value;
    const unitario = valor === null || valor === '' ? null : Number(valor);
    fila.get('despor')?.setValue(this.porcentajeDesde(unitario, precio), { emitEvent: false });
    this.recalcularImporte(fila);
  }

  /** IVA CONTENIDO en el importe (10% = /11, 5% = /21), igual que la factura. */
  ivaLinea(fila: any): number {
    const tasa = Number(fila.cmd_tasa) || 0;
    const importe = Number(fila.importe) || 0;
    return tasa > 0 ? Math.round((importe * tasa) / (100 + tasa)) : 0;
  }

  private get lineasIncluidas(): any[] {
    return this.detalle.controls.filter((c) => c.value.incluir).map((c) => c.value);
  }

  get totalGravada(): number {
    return this.lineasIncluidas.reduce((s, l) => s + (Number(l.importe) || 0), 0);
  }

  get totalIva(): number {
    return this.lineasIncluidas.reduce((s, l) => s + this.ivaLinea(l), 0);
  }

  get totalGeneral(): number {
    return this.totalGravada;   // el IVA ya esta incluido
  }


  // ==================== Timbrado: selects y alta rápida ====================

  get timbradoSeleccionado(): TimbradoProveedorOption | undefined {
    return this.timbrados.find((t) => t.tpv_id === this.form.get('ncr_tpvid')!.value);
  }

  get vtoTimbrado(): string {
    return fmt(this.timbradoSeleccionado?.tpv_fechfn);
  }

  get placeholderTimbrado(): string {
    if (this.cargandoConfig || !this.tipoNotaCredito) return 'Cargando...';
    if (this.cargandoTimbrados) return 'Cargando...';
    return this.timbrados.length ? 'Seleccione un timbrado' : 'Sin timbrados: registre uno con +';
  }

  etiquetaTimbrado(t: TimbradoProveedorOption): string {
    return t.tpv_numero;
  }

  timbradoInutilizable(t: TimbradoProveedorOption): boolean {
    return t.tpv_fechfn < this.fechaMinima;
  }

  get todosVencidos(): boolean {
    return this.timbrados.length > 0 && this.timbrados.every((t) => t.tpv_fechfn < this.hoy);
  }

  campoTimbradoInvalido(campo: string): boolean {
    const c = this.timbradoForm.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  abrirNuevoTimbrado(): void {
    this.timbradoForm.reset({ tpv_numero: '', tpv_fechfn: '' });
    this.mostrarNuevoTimbrado = true;
  }

  guardarNuevoTimbrado(): void {
    if (!this.proveedorId || !this.tipoNotaCredito) {
      this.toastr.error('Falta información del proveedor', 'Error');
      return;
    }
    if (this.timbradoForm.invalid) {
      this.timbradoForm.markAllAsTouched();
      return;
    }

    this.guardandoTimbrado = true;
    this.comprasService.crearTimbradoProveedor({
      ...this.timbradoForm.value,
      tpv_proid: this.proveedorId,
      tpv_tcoid: this.tipoNotaCredito.tco_id,
    }).subscribe({
      next: (t) => {
        this.guardandoTimbrado = false;
        this.timbrados = [t, ...this.timbrados];
        this.form.get('ncr_tpvid')!.setValue(t.tpv_id);
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

    // ==================== Motivo y su efecto sobre el detalle ====================

  get motivoSeleccionado(): MotivoNotaCredito | undefined {
    return this.motivos.find((m) => m.mnc_id === this.form.get('ncr_mncid')!.value);
  }

  /** Unica fuente de verdad del comportamiento del formulario. */
  get afectaStock(): boolean {
    return this.motivoSeleccionado?.mnc_afesto === 1;
  }

  /** Tope de cantidad: en devoluciones tambien limita el stock fisico. */
  maximoCantidad(fila: any): number {
    return this.afectaStock
      ? Math.min(Number(fila.saldo_acreditable) || 0, Number(fila.stock_disponible) || 0)
      : Number(fila.saldo_acreditable) || 0;
  }

  sinStockParaDevolver(fila: any): boolean {
    return this.afectaStock && (Number(fila.stock_disponible) || 0) <= 0;
  }

  /**
   * Al cambiar el motivo se reconfigura todo el detalle:
   * - Afecta stock  -> importe unitario = precio facturado, campos bloqueados.
   * - No afecta     -> importe unitario editable, se limpia lo anterior.
   */
  onMotivoChange(): void {
    const afecta = this.afectaStock;

    this.detalle.controls.forEach((fila) => {
      const precio = Number(fila.get('precio_neto')?.value) || 0;

      if (afecta) {
        fila.get('descuento_unitario')?.setValue(precio, { emitEvent: false });
        fila.get('despor')?.setValue(100, { emitEvent: false });
        fila.get('despor')?.disable({ emitEvent: false });
        fila.get('descuento_unitario')?.disable({ emitEvent: false });

        // Si lo cargado supera el stock disponible, se recorta.
        const max = this.maximoCantidad(fila.value);
        if (Number(fila.get('cantidad')?.value) > max) {
          fila.get('cantidad')?.setValue(max || null, { emitEvent: false });
        }
        if (this.sinStockParaDevolver(fila.value)) {
          fila.get('incluir')?.setValue(false, { emitEvent: false });
        }
      } else {
        fila.get('despor')?.enable({ emitEvent: false });
        fila.get('descuento_unitario')?.enable({ emitEvent: false });
        fila.get('descuento_unitario')?.setValue(null, { emitEvent: false });
        fila.get('despor')?.setValue(null, { emitEvent: false });
      }

      this.recalcularImporte(fila);
    });
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

  get digitosNumero(): number {
    return String(this.form.get('ncr_numero')!.value ?? '').replace(/\D/g, '').length;
  }

  get errorNumero(): string | null {
    const e = this.erroresVisibles('ncr_numero');
    if (!e) return null;
    if (e['backend']) return e['backend'];
    if (e['required']) return 'El número de la Nota de Crédito es obligatorio.';
    return `Faltan ${DIGITOS_NUMERO - this.digitosNumero} dígito(s). Formato: 000-000-0000000.`;
  }

  get errorFecha(): string | null {
    const e = this.erroresVisibles('ncr_fecha');
    if (!e) return null;
    if (e['backend']) return e['backend'];
    if (e['required']) return 'La fecha es obligatoria.';
    if (e['futura']) return 'La fecha no puede ser posterior a hoy.';
    if (e['anterior']) return `No puede ser anterior a la fecha de la factura (${fmt(this.fechaMinima)}).`;
    return null;
  }

  get errorTimbrado(): string | null {
    const e = this.erroresVisibles('ncr_tpvid');
    if (!e) return null;
    if (e['backend']) return e['backend'];
    if (e['vencido']) return `Timbrado vencido el ${fmt(e['vencido'])}. Registre uno nuevo con el botón +.`;
    if (e['required']) return 'Seleccione el timbrado de la Nota de Crédito.';
    return null;
  }

  get errorMotivo(): string | null {
    const e = this.erroresVisibles('ncr_mncid');
    if (!e) return null;
    if (e['backend']) return e['backend'];
    if (e['required']) return 'Seleccione el motivo de la Nota de Crédito.';
    return null;
  }

  onNumeroInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const formateado = formatearNumero(input.value);
    input.value = formateado;
    this.form.get('ncr_numero')!.setValue(formateado);
  }

  // ==================== Guardar ====================

    guardar(): void {
    const seleccionadas = this.detalle.getRawValue().filter((l: any) => l.incluir);

    if (!this.form.get('ncr_mncid')!.value) {
      this.form.markAllAsTouched();
      this.toastr.error('Seleccione el motivo de la Nota de Crédito', 'Validación');
      return;
    }

    if (seleccionadas.length === 0) {
      this.toastr.error('Seleccione al menos un ítem a acreditar', 'Detalle vacío');
      return;
    }

    const afecta = this.afectaStock;

    for (const l of seleccionadas) {
      const max = this.maximoCantidad(l);

      if (!l.cantidad || l.cantidad <= 0 || l.cantidad > max) {
        this.toastr.error(`Cantidad inválida en "${l.cmd_descri}" (máx. ${max})`, 'Revise el detalle');
        return;
      }
      if (!afecta && (!l.descuento_unitario || l.descuento_unitario <= 0)) {
        this.toastr.error(`Falta el importe unitario en "${l.cmd_descri}"`, 'Revise el detalle');
        return;
      }
      if (!afecta && l.descuento_unitario > l.precio_neto) {
        this.toastr.error(`El importe unitario de "${l.cmd_descri}" supera el precio facturado`, 'Revise el detalle');
        return;
      }
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toastr.error('Revise los campos marcados en rojo', 'Validación');
      return;
    }

    this.guardando = true;

    const payload = {
      ncr_tpvid:  this.form.value.ncr_tpvid,
      ncr_numero: this.form.value.ncr_numero,
      ncr_fecha:  this.form.value.ncr_fecha,
      ncr_mncid:  this.form.value.ncr_mncid,
      ncr_obs:    this.form.value.ncr_obs,
      // El importe lo calcula el backend: no se envia base_gravada ni iva.
      detalle: seleccionadas.map((l: any) => ({
        cmd_id: l.cmd_id,
        cantidad: l.cantidad,
        ...(afecta ? {} : { descuento_unitario: l.descuento_unitario }),
      })),
    };

    this.service.create(this.compra!.com_id!, payload).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Nota de Crédito registrada', 'Éxito');
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
        // 409: regla de negocio (ej. stock insuficiente al confirmar)
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar la Nota de Crédito', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}