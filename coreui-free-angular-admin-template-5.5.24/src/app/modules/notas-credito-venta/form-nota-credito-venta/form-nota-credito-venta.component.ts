import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  AbstractControl, FormArray, FormBuilder, FormGroup, ReactiveFormsModule,
  ValidationErrors, Validators,
} from '@angular/forms';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { ToastrService } from 'ngx-toastr';
import {
  BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import { Venta } from '../../facturas-venta/models/venta.model';
import { VentasService } from '../../facturas-venta/service/ventas.service';
import {
  ConfigPorFactura, DepositoOption, LineaFacturaNC, MotivoNotaCredito,
} from '../models/nota-credito-venta.model';
import { NotasCreditoVentaService } from '../service/notas-credito-venta.service';

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

/**
 * Emisión de una Nota de Crédito de venta.
 *
 * El número y el timbrado no se piden: los asigna el backend desde el
 * talonario propio de Nota de Crédito.
 *
 * El MOTIVO manda sobre todo el formulario (y el detalle está bloqueado
 * hasta elegirlo):
 *   - mnc_afesto : devolución (importe fijo = precio facturado, y hay que
 *                  decidir el destino de la mercadería) o ajuste (importe
 *                  editable, acotado al precio facturado, sin stock).
 *   - mnc_canfij : la cantidad es el saldo de unidades y no se tipea.
 *   - mnc_obsreq : la observación es obligatoria.
 *   - mnc_destex : el destino no tiene sugerencia (defectuosos).
 *
 * "Cantidad" e "Importe unitario" arrancan VACÍOS, con el máximo como
 * placeholder: el usuario los escribe, y nunca pueden quedar por encima
 * del saldo acreditable ni del precio facturado.
 */
@Component({
  selector: 'app-form-nota-credito-venta',
  standalone: true,
  imports: [
    CommonModule, FormsModule, ReactiveFormsModule, ButtonModule,
    FormModule, ModalModule, SpinnerModule, TableModule, BadgeModule,
  ],
  templateUrl: './form-nota-credito-venta.component.html',
})
export class FormNotaCreditoVentaComponent implements OnChanges {
  @Input() visible = false;
  /** Si viene, el modal abre directo en el paso 2 (atajo desde la factura). */
  @Input() venId: number | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  readonly hoy = fechaLocal();
  readonly fmt = fmt;

  paso: 'factura' | 'detalle' = 'detalle';
  form: FormGroup;
  guardando = false;
  cargandoConfig = false;

  // --- Paso 1: elegir factura ---
  buscarFactura = '';
  facturas: Venta[] = [];
  buscandoFacturas = false;
  private busqueda = new Subject<string>();

  // --- Paso 2: datos de la factura elegida ---
  factura: ConfigPorFactura | null = null;
  motivos: MotivoNotaCredito[] = [];
  fechaMinima = this.hoy;

  private readonly fechaNCValidator = (c: AbstractControl): ValidationErrors | null => {
    const f: string = c.value;
    if (!f) return null;
    if (f > this.hoy) return { futura: true };
    if (f < this.fechaMinima) return { anterior: true };
    return null;
  };

  constructor(
    private fb: FormBuilder,
    private service: NotasCreditoVentaService,
    private ventasService: VentasService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      ncv_mncid:   [null, [Validators.required]],
      ncv_fecha:   ['', [Validators.required, this.fechaNCValidator]],
      // null = sin elegir · 0 = NO reingresa al stock (baja) · n = id del depósito
      ncv_destino: [null as number | null],
      ncv_obs:     ['', [Validators.maxLength(255)]],
      detalle:     this.fb.array([]),
    });

    this.busqueda
      .pipe(debounceTime(400), distinctUntilChanged())
      .subscribe(() => this.cargarFacturas());
  }

  get detalle(): FormArray {
    return this.form.get('detalle') as FormArray;
  }

  // ==================== Apertura ====================

  ngOnChanges(changes: SimpleChanges): void {
    if (!(changes['visible'] && this.visible)) return;

    this.guardando = false;
    this.factura = null;
    this.motivos = [];
    this.detalle.clear();
    this.form.reset({ ncv_mncid: null, ncv_fecha: this.hoy, ncv_destino: null, ncv_obs: '' });
    this.aplicarReglasDeMotivo();   // sin motivo: limpia validadores de la apertura anterior

    if (this.venId) {
      this.paso = 'detalle';
      this.cargarConfig(this.venId);
    } else {
      this.paso = 'factura';
      this.buscarFactura = '';
      this.facturas = [];
      this.cargarFacturas();
    }
  }

  // ==================== Paso 1: facturas ====================

  onBuscarChange(): void {
    this.busqueda.next(this.buscarFactura);
  }

  private cargarFacturas(): void {
    this.buscandoFacturas = true;

    this.ventasService.getAll({ search: this.buscarFactura }).subscribe({
      next: (res) => {
        this.facturas = res.filter((v) => v.ven_estado !== 9).slice(0, 20);
        this.buscandoFacturas = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar las facturas', 'Error');
        this.buscandoFacturas = false;
      },
    });
  }

  elegirFactura(v: Venta): void {
    this.paso = 'detalle';
    this.cargarConfig(v.ven_id);
  }

  volverAFacturas(): void {
    this.paso = 'factura';
    this.factura = null;
    this.detalle.clear();
  }

  // ==================== Paso 2: config y detalle ====================

  private cargarConfig(venId: number): void {
    this.cargandoConfig = true;

    this.service.getConfigPorFactura(venId).subscribe({
      next: (res) => {
        this.factura = res;
        this.motivos = res.motivos ?? [];
        this.fechaMinima = res.ven_fchaem;

        const disponibles = (res.lineas ?? []).filter((l) => l.saldo_acreditable > 0);
        disponibles.forEach((l) => this.detalle.push(this.filaDetalle(l)));

        this.form.get('ncv_fecha')!.setValue(this.hoy);
        this.form.get('ncv_fecha')!.updateValueAndValidity();

        if (disponibles.length === 0) {
          this.toastr.info('Esta factura ya está acreditada por completo', 'Sin saldo');
        }

        this.cargandoConfig = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar el detalle de la factura', 'Error');
        this.cargandoConfig = false;
      },
    });
  }

  private filaDetalle(l: LineaFacturaNC): FormGroup {
    return this.fb.group({
      vnd_id:            [l.vnd_id],
      incluir:           [false],
      vnd_prdid:         [l.vnd_prdid],
      vnd_codigo:        [l.vnd_codigo],
      vnd_descripcion:   [l.vnd_descripcion],
      vnd_umedida:       [l.vnd_umedida],
      vnd_tasa:          [l.vnd_tasa],
      vnd_precio:        [l.vnd_precio],
      admite_decimales:  [l.admite_decimales],
      saldo_acreditable: [l.saldo_acreditable],
      saldo_importe:     [l.saldo_importe],
      // Nacen vacíos y bloqueados: se habilitan al tildar la línea.
      cantidad:          [{ value: null as number | null, disabled: true }],
      precio:            [{ value: null as number | null, disabled: true }],
      importe:           [0],
      aviso:             [''],   // mensaje bajo "Cantidad"
      avisoPrecio:       [''],   // mensaje bajo "Importe unitario"
    });
  }

  // ==================== Motivo y sus reglas ====================

  get motivoSeleccionado(): MotivoNotaCredito | undefined {
    return this.motivos.find((m) => m.mnc_id === this.form.get('ncv_mncid')!.value);
  }

  /** El detalle solo se habilita cuando ya hay un motivo elegido. */
  get detalleHabilitado(): boolean {
    return !!this.motivoSeleccionado;
  }

  /** Devolución: mueve mercadería. Ajuste: solo el importe. */
  get afectaStock(): boolean {
    return this.motivoSeleccionado?.mnc_afesto === 1;
  }

  /** Error de precio: la cantidad es el saldo de unidades y no se tipea. */
  get cantidadFija(): boolean {
    return this.motivoSeleccionado?.mnc_canfij === 1;
  }

  get exigeObs(): boolean {
    return this.motivoSeleccionado?.mnc_obsreq === 1;
  }

  get depositos(): DepositoOption[] {
    return this.factura?.depositos ?? [];
  }

  private get depositoPrincipalId(): number | null {
    return this.depositos.find((d) => d.dpo_princi === 1)?.dpo_id ?? null;
  }

  /** Validadores que dependen del motivo: observación y destino de la mercadería. */
  private aplicarReglasDeMotivo(): void {
    const m = this.motivoSeleccionado;

    const obs = this.form.get('ncv_obs')!;
    obs.setValidators(
      m?.mnc_obsreq === 1
        ? [Validators.required, Validators.minLength(5), Validators.maxLength(255)]
        : [Validators.maxLength(255)],
    );
    obs.updateValueAndValidity({ emitEvent: false });

    const destino = this.form.get('ncv_destino')!;
    if (m?.mnc_afesto === 1) {
      destino.setValidators([Validators.required]);   // 0 (no reingresa) es un valor válido
      // Defectuosos: sin sugerencia, para que no vuelvan solos al stock vendible.
      destino.setValue(m.mnc_destex === 1 ? null : this.depositoPrincipalId, { emitEvent: false });
    } else {
      destino.clearValidators();
      destino.setValue(null, { emitEvent: false });
    }
    destino.updateValueAndValidity({ emitEvent: false });
  }

  /**
   * Al elegir (o cambiar) el motivo se reconfigura todo. Se limpia lo que
   * hubiera tildado: cambiar de devolución a descuento cambia el significado
   * de cada línea, y arrastrar una cantidad cargada bajo otro criterio es la
   * forma más fácil de emitir un importe que el usuario ya no está mirando.
   */
  onMotivoChange(): void {
    this.aplicarReglasDeMotivo();

    this.detalle.controls.forEach((fila) => {
      fila.get('incluir')?.setValue(false, { emitEvent: false });
      fila.get('cantidad')?.setValue(null, { emitEvent: false });
      fila.get('precio')?.setValue(null, { emitEvent: false });
      fila.get('aviso')?.setValue('', { emitEvent: false });
      fila.get('avisoPrecio')?.setValue('', { emitEvent: false });
      this.aplicarEstadoFila(fila);
      this.recalcular(fila);
    });
  }

  // ==================== Estado y cálculo por línea ====================

  /**
   * Habilita o bloquea los inputs de una fila según el motivo y el tilde.
   * Se hace sobre los controles (no con [disabled] en la plantilla): sobre
   * formControlName ese atributo no es confiable.
   */
  private aplicarEstadoFila(fila: AbstractControl): void {
    const incluida = !!fila.get('incluir')?.value;
    this.alternar(fila.get('cantidad'), incluida && !this.cantidadFija);
    this.alternar(fila.get('precio'), incluida && !this.afectaStock);
  }

  private alternar(control: AbstractControl | null, habilitar: boolean): void {
    if (!control) return;
    if (habilitar) control.enable({ emitEvent: false });
    else control.disable({ emitEvent: false });
  }

  private recalcular(fila: AbstractControl): void {
    const cantidad = Number(fila.get('cantidad')?.value) || 0;

    if (this.afectaStock) {
      // Devolución: parte proporcional del saldo de importe de la línea. Si
      // devuelve todas las unidades que quedan es el saldo exacto. Misma
      // regla que el backend (el backend es el que manda).
      const saldoCant = Number(fila.get('saldo_acreditable')?.value) || 0;
      const saldoImp  = Number(fila.get('saldo_importe')?.value) || 0;

      let importe = 0;
      if (cantidad > 0 && saldoCant > 0) {
        importe = Math.abs(cantidad - saldoCant) < 0.0001
          ? saldoImp
          : Math.round((saldoImp * cantidad) / saldoCant);
      }

      fila.get('importe')?.setValue(importe);
      return;
    }

    const precio = Number(fila.get('precio')?.value) || 0;
    fila.get('importe')?.setValue(Math.round(precio * cantidad));
  }

  /** Tope de cantidad de una fila: el saldo acreditable que calcula el backend. */
  maximoCantidad(fila: any): number {
    return Number(fila.saldo_acreditable) || 0;
  }

    /** Precio neto vigente por unidad: lo que valen hoy las unidades que quedan. */
  precioVigente(fila: any): number {
    const cantidad = Number(fila.saldo_acreditable) || 0;
    return cantidad > 0 ? Math.round((Number(fila.saldo_importe) / cantidad) * 100) / 100 : 0;
  }

  /** True si la línea ya tuvo descuentos y por eso su precio vigente es menor al facturado. */
  tieneDescuentoPrevio(fila: any): boolean {
    return this.afectaStock && this.precioVigente(fila) < Number(fila.vnd_precio) - 0.01;
  }

  /**
   * Tildar una línea NO escribe la cantidad: queda vacía, con el máximo
   * como placeholder, para que la tipee el usuario. Solo se completa sola
   * cuando el motivo la determina (error de precio) o el importe es fijo.
   */
  onIncluirChange(fila: AbstractControl): void {
    if (!this.detalleHabilitado) return;

    const incluida = !!fila.get('incluir')?.value;

    fila.get('cantidad')?.setValue(null, { emitEvent: false });
    fila.get('precio')?.setValue(null, { emitEvent: false });
    fila.get('aviso')?.setValue('', { emitEvent: false });
    fila.get('avisoPrecio')?.setValue('', { emitEvent: false });

    if (incluida) {
      if (this.cantidadFija) {
        fila.get('cantidad')?.setValue(this.maximoCantidad(fila.getRawValue()), { emitEvent: false });
      }
      if (this.afectaStock) {
        fila.get('precio')?.setValue(this.precioVigente(fila.getRawValue()) || null, { emitEvent: false });
      }
    }

    this.aplicarEstadoFila(fila);
    this.recalcular(fila);
  }

  /**
   * La cantidad no puede quedar por encima del saldo acreditable ni, en
   * unidades enteras, con decimales: si el usuario se pasa, se recorta al
   * máximo y se avisa debajo del campo.
   */
  onCantidadChange(fila: AbstractControl): void {
    const control = fila.get('cantidad')!;
    const aviso   = fila.get('aviso')!;
    const max     = this.maximoCantidad(fila.getRawValue());
    const entera  = !fila.get('admite_decimales')?.value;

    aviso.setValue('', { emitEvent: false });

    const crudo = control.value;
    if (crudo === null || crudo === '') {
      this.recalcular(fila);
      return;
    }

    let n = Number(crudo);

    if (n < 0) {
      n = 0;
      aviso.setValue('La cantidad debe ser mayor a cero.', { emitEvent: false });
    }

    if (entera && !Number.isInteger(n)) {
      n = Math.trunc(n);
      aviso.setValue('Esta unidad solo admite cantidades enteras.', { emitEvent: false });
    }

    if (n > max) {
      n = max;
      aviso.setValue(`Máximo permitido: ${max}`, { emitEvent: false });
    }

    if (n !== Number(crudo)) {
      control.setValue(n, { emitEvent: false });
    }

    this.recalcular(fila);
  }

  /** El importe unitario nunca puede superar el precio facturado. */
  onPrecioChange(fila: AbstractControl): void {
    const control = fila.get('precio')!;
    const aviso   = fila.get('avisoPrecio')!;
    const maximo  = Number(fila.get('vnd_precio')?.value) || 0;

    aviso.setValue('', { emitEvent: false });

    const crudo = control.value;
    if (crudo === null || crudo === '') {
      this.recalcular(fila);
      return;
    }

    let n = Number(crudo);

    if (n < 0) n = 0;

    if (n > maximo) {
      n = maximo;
      aviso.setValue(`No puede superar el precio facturado (${maximo}).`, { emitEvent: false });
    }

    if (n !== Number(crudo)) {
      control.setValue(n, { emitEvent: false });
    }

    this.recalcular(fila);
  }

  /** IVA CONTENIDO en el importe (10% = x10/110, 5% = x5/105), igual que la factura. */
  ivaLinea(fila: any): number {
    const tasa    = Number(fila.vnd_tasa) || 0;
    const importe = Number(fila.importe) || 0;
    return tasa > 0 ? Math.round((importe * tasa) / (100 + tasa)) : 0;
  }

  private get lineasIncluidas(): any[] {
    return this.detalle.getRawValue().filter((l: any) => l.incluir);
  }

  get totalGravado(): number {
    return this.lineasIncluidas.reduce((s, l) => s + (Number(l.importe) || 0), 0);
  }

  get totalIva(): number {
    return this.lineasIncluidas.reduce((s, l) => s + this.ivaLinea(l), 0);
  }

  get saldoFactura(): number {
    if (!this.factura) return 0;
    return Number(this.factura.ven_total) - Number(this.factura.ven_acreditado);
  }

  // ==================== Errores ====================

  private erroresVisibles(campo: string): ValidationErrors | null {
    const c = this.form.get(campo);
    return c && c.invalid && (c.touched || c.dirty) ? c.errors : null;
  }

  campoInvalido(campo: string): boolean {
    return !!this.erroresVisibles(campo);
  }

  get errorFecha(): string | null {
    const e = this.erroresVisibles('ncv_fecha');
    if (!e) return null;
    if (e['backend']) return e['backend'];
    if (e['required']) return 'La fecha es obligatoria.';
    if (e['futura']) return 'La fecha no puede ser posterior a hoy.';
    if (e['anterior']) return `No puede ser anterior a la fecha de la factura (${fmt(this.fechaMinima)}).`;
    return null;
  }

  get errorMotivo(): string | null {
    const e = this.erroresVisibles('ncv_mncid');
    if (!e) return null;
    if (e['backend']) return e['backend'];
    if (e['required']) return 'Seleccione el motivo de la nota de crédito.';
    return null;
  }

  get errorObs(): string | null {
    const e = this.erroresVisibles('ncv_obs');
    if (!e) return null;
    if (e['backend']) return e['backend'];
    if (e['required'] || e['minlength']) {
      return 'Este motivo exige una observación que explique el caso (mínimo 5 caracteres).';
    }
    return null;
  }

  get errorDestino(): string | null {
    const e = this.erroresVisibles('ncv_destino');
    if (!e) return null;
    if (e['backend']) return e['backend'];
    if (e['required']) return 'Indique si la mercadería reingresa al stock y a qué depósito.';
    return null;
  }

  // ==================== Guardar ====================

  guardar(): void {
    if (!this.factura) return;

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toastr.error('Revise los campos marcados en rojo', 'Validación');
      return;
    }

    const seleccionadas = this.lineasIncluidas;

    if (seleccionadas.length === 0) {
      this.toastr.error('Seleccione al menos un ítem a acreditar', 'Detalle vacío');
      return;
    }

    const afecta = this.afectaStock;

    for (const l of seleccionadas) {
      const max  = Number(l.saldo_acreditable) || 0;
      const cant = Number(l.cantidad);

      if (!cant || cant <= 0 || cant > max + 0.0001) {
        this.toastr.error(`Cantidad inválida en "${l.vnd_descripcion}" (máx. ${max})`, 'Revise el detalle');
        return;
      }

      if (!l.admite_decimales && !Number.isInteger(cant)) {
        this.toastr.error(`"${l.vnd_descripcion}" solo admite cantidades enteras`, 'Revise el detalle');
        return;
      }

      if (!afecta) {
        if (!l.precio || l.precio < 0.01) {
          this.toastr.error(`Falta el importe unitario en "${l.vnd_descripcion}"`, 'Revise el detalle');
          return;
        }
        if (l.precio > Number(l.vnd_precio) + 0.01) {
          this.toastr.error(
            `El importe unitario de "${l.vnd_descripcion}" supera el precio facturado`, 'Revise el detalle',
          );
          return;
        }
      }

      if (Number(l.importe) > Number(l.saldo_importe) + 1) {
        this.toastr.error(
          `El importe de "${l.vnd_descripcion}" supera lo que queda acreditable de la línea (${l.saldo_importe})`,
          'Revise el detalle',
        );
        return;
      }
    }

    this.guardando = true;

    const destino = this.form.value.ncv_destino as number | null;

    this.service.create({
      ncv_venid: this.factura.ven_id,
      ncv_mncid: this.form.value.ncv_mncid,
      ncv_fecha: this.form.value.ncv_fecha,
      ncv_obs:   this.form.value.ncv_obs || null,
      // 0 = NO reingresa al stock (baja). Solo aplica a motivos que mueven mercadería.
      ...(afecta ? { ncv_reingresa: destino !== 0, ncv_depid: destino || null } : {}),
      // El importe y el número los calcula el backend: no se envían.
      detalle: seleccionadas.map((l: any) => ({
        vnd_id: l.vnd_id,
        cantidad: l.cantidad,
        ...(afecta ? {} : { precio: l.precio }),
      })),
    }).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Nota de crédito emitida', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;

        if (err.status === 422 && err.error?.errors) {
          // El backend nombra ncv_reingresa / ncv_depid; en el form es un solo select.
          const alias: Record<string, string> = { ncv_reingresa: 'ncv_destino', ncv_depid: 'ncv_destino' };

          Object.keys(err.error.errors).forEach((campo) => {
            const control = this.form.get(alias[campo] ?? campo.split('.')[0]);
            if (control) {
              control.setErrors({ backend: err.error.errors[campo][0] });
              control.markAsTouched();
            }
          });

          this.toastr.error(err.error.errors[Object.keys(err.error.errors)[0]][0], 'Validación');
          return;
        }

        // 409: regla de negocio (sin timbrado de NC, sin saldo, fecha fuera de vigencia...)
        this.toastr.error(err?.error?.message ?? 'No se pudo emitir la nota de crédito', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}