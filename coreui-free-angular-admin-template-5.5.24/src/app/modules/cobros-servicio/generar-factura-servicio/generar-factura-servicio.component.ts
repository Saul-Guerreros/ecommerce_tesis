import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Observable, of, switchMap } from 'rxjs';
import { AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { CobroDetalle, MEDIOS_MOSTRADOR, MedioDeclarado, MetodoPagoOpcion, RespuestaCobro } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';
import { DatosFacturacionServicioComponent, ERROR_FACTURACION } from '../datos-facturacion-servicio/datos-facturacion-servicio.component';

/**
 *   anticipo      mostrador: el cajero declara los medios + factura de anticipo (tipo 9).
 *   anticipo-web  el anticipo web ya validado: solo emite su factura.
 *   final         equipo LISTO: si queda saldo, el cajero declara sus medios
 *                 por el importe EXACTO; si no, se usan los pagos web.
 */
export type ModoFactura = 'anticipo' | 'anticipo-web' | 'final';

/**
 * Genera una factura del servicio. Ciclo comprobante -> FACTURA -> cobro:
 * los medios son intenciones; el dinero se registra después con "Cobrar".
 * Los datos fiscales se guardan antes de emitir (la primera factura, sea
 * anticipo o final, los congela: el anticipo y la final van al mismo cliente).
 */
@Component({
  selector: 'app-generar-factura-servicio',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ModalModule, ButtonModule, FormModule, AlertModule, SpinnerModule, DatosFacturacionServicioComponent],
  templateUrl: './generar-factura-servicio.component.html',
})
export class GenerarFacturaServicioComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() detalle: CobroDetalle | null = null;
  @Input() modo: ModoFactura = 'final';
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<CobroDetalle>();

  @ViewChild(DatosFacturacionServicioComponent) datosFacturacion?: DatosFacturacionServicioComponent;

  metodos: MetodoPagoOpcion[] = [];
  form: FormGroup;
  errores: string[] = [];
  guardando = false;

  constructor(private fb: FormBuilder, private service: CobrosServicioService, private toastr: ToastrService) {
    this.form = this.fb.group({ medios: this.fb.array([]) });
  }

  ngOnInit(): void {
    this.service.config().subscribe({
      next: (res) => (this.metodos = res.data.metodos.filter((m) => MEDIOS_MOSTRADOR.includes(m.mpa_codigo))),
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.detalle) {
      this.errores = [];
      this.medios.clear();
      if (this.modo === 'anticipo') this.agregarMedio();
      if (this.modo === 'final' && this.pideMedios) this.agregarMedio(this.saldo);
    }
  }

  // ---------- Medios ----------

  get medios(): FormArray { return this.form.get('medios') as FormArray; }

  agregarMedio(monto: number | null = null): void {
    this.medios.push(this.fb.group({
      mpa_codigo: ['', Validators.required],
      monto: [monto, [Validators.required, Validators.min(1)]],
    }));
  }

  quitarMedio(i: number): void {
    if (this.medios.length > 1) this.medios.removeAt(i);
  }

  get suma(): number {
    return this.medios.controls.reduce((t, c) => t + Number(c.get('monto')?.value ?? 0), 0);
  }

  // ---------- Reglas de la vista (las definitivas, en el backend) ----------

  get saldo(): number { return this.detalle?.acciones.saldo_por_declarar ?? 0; }
  get tope(): number | null { return this.detalle?.acciones.tope_anticipo ?? null; }

  get pideMedios(): boolean {
    return this.modo === 'anticipo' || (this.modo === 'final' && this.saldo > 0 && !!this.detalle?.acciones.declarar_medios);
  }

  /** Anticipo que se aplicará en la final (el backend aplica hasta el total). */
  get anticipoAplicable(): number {
    const disponible = this.detalle?.facturas.anticipo?.anticipo?.disponible ?? 0;
    return Math.min(disponible, this.detalle?.resumen.total ?? 0);
  }

  get bloqueo(): string | null {
    if (this.modo === 'final' && this.saldo > 0 && !this.detalle?.acciones.declarar_medios) {
      return 'Queda saldo sin medio de pago: lo declara un cajero de la sucursal del equipo.';
    }
    return null;
  }

  get errorSuma(): string | null {
    if (!this.pideMedios || this.medios.length === 0) return null;
    if (this.modo === 'final' && this.suma !== this.saldo) {
      return `Los medios suman Gs. ${this.formatoGs(this.suma)} y el saldo es Gs. ${this.formatoGs(this.saldo)}.`;
    }
    if (this.modo === 'anticipo' && this.tope !== null && this.suma > this.tope) {
      return `El anticipo no puede superar lo aprobado en el presupuesto (Gs. ${this.formatoGs(this.tope)}).`;
    }
    return null;
  }

  get titulo(): string {
    return this.modo === 'final' ? 'Generar factura' : this.modo === 'anticipo' ? 'Registrar anticipo' : 'Generar factura de anticipo';
  }

  // ---------- Guardar ----------

  guardar(): void {
    if (this.guardando || !this.detalle || this.bloqueo) return;
    if ((this.pideMedios && this.form.invalid) || this.errorSuma || this.datosFacturacion?.cargando) {
      this.form.markAllAsTouched();
      return;
    }

    const sseId = this.detalle.sse_id;
    const pagos: MedioDeclarado[] = this.pideMedios
      ? this.medios.value.map((m: MedioDeclarado) => ({ mpa_codigo: m.mpa_codigo, monto: Number(m.monto) }))
      : [];

    const accion$: Observable<RespuestaCobro> =
      this.modo === 'anticipo' ? this.service.declararAnticipo(sseId, pagos)
      : this.modo === 'anticipo-web' ? this.service.generarFacturaAnticipo(sseId)
      : this.service.generarFactura(sseId, pagos);

    const facturacion$ = this.datosFacturacion ? this.datosFacturacion.guardar() : of(null);
    this.errores = [];
    this.guardando = true;

    // 1) datos fiscales  2) factura. Si el 1 falla, no se emite nada.
    facturacion$.pipe(switchMap(() => accion$)).subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message, this.titulo);
        this.guardado.emit(res.data);
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err === ERROR_FACTURACION) return;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        this.errores = errores
          ? Object.values(errores).map((m) => m[0])
          : [err?.error?.message ?? 'No se pudo generar la factura'];
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  formatoGs(valor: number | null | undefined): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}