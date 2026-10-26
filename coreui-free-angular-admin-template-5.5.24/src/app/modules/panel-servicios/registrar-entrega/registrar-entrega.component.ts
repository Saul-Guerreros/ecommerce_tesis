import { Component, DestroyRef, EventEmitter, Input, OnChanges, Output, SimpleChanges, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { of, switchMap } from 'rxjs';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { EntregaConfig, PanelFila } from '../models/panel-servicio.model';
import { PanelServiciosService } from '../service/panel-servicios.service';
import { CobroDetalle, MetodoPagoOpcion } from '../../cobros-servicio/models/cobro-servicio.model';
import { CobrosServicioService } from '../../cobros-servicio/service/cobros-servicio.service';
import {
  DatosFacturacionServicioComponent, ERROR_FACTURACION,
} from '../../cobros-servicio/datos-facturacion-servicio/datos-facturacion-servicio.component';

/**
 * A5 - Registrar entrega. Receptor (titular o tercero con C.I.), checklist
 * de accesorios devueltos y, al terminar, la constancia de retiro.
 *
 * "Cobrar y entregar": si el cobro está pendiente (regla 7) y el usuario
 * puede cobrar en esta sucursal (acciones.cobrar, lo decide el backend), el
 * modal agrega el bloque del cobro del saldo y lo envía junto con la entrega
 * en UNA transacción. Sin ese permiso, la regla 7 sigue bloqueando igual.
 *
 * Ese cobro completa el total, así que emite la factura: antes se guardan los
 * datos fiscales (CI o RUC) con el bloque reutilizable de cobros-servicio.
 */
@Component({
  selector: 'app-registrar-entrega',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule, DatosFacturacionServicioComponent],
  templateUrl: './registrar-entrega.component.html',
})
export class RegistrarEntregaComponent implements OnChanges {
  private readonly destroyRef = inject(DestroyRef);

  @Input() visible = false;
  @Input() fila: PanelFila | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  /** Solo existe cuando se cobra y entrega en la misma operación. */
  @ViewChild(DatosFacturacionServicioComponent) datosFacturacion?: DatosFacturacionServicioComponent;

  cfg: EntregaConfig | null = null;
  form: FormGroup;

  // Cobrar y entregar
  cobro: CobroDetalle | null = null;
  metodos: MetodoPagoOpcion[] = [];
  cobroForm: FormGroup;
  archivoCobro: File | null = null;
  errorArchivoCobro: string | null = null;
  cargando = false;
  guardando = false;
  entregado = false;
  devueltos = new Set<number>();

  constructor(
    private fb: FormBuilder,
    private service: PanelServiciosService,
    private cobros: CobrosServicioService,
    private toastr: ToastrService,
  ) {
    this.cobroForm = this.fb.group({
      mpa_codigo: ['', Validators.required],
      nro_transaccion: ['', Validators.maxLength(50)],
      banco: ['', Validators.maxLength(60)],
      observacion: ['', Validators.maxLength(255)],
    });
    this.cobroForm.get('mpa_codigo')!.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      const ref = this.cobroForm.get('nro_transaccion')!;
      ref.setValidators(this.cobroRequiereReferencia ? [Validators.required, Validators.maxLength(50)] : [Validators.maxLength(50)]);
      ref.updateValueAndValidity({ emitEvent: false });
    });

    this.form = this.fb.group({
      modalidad: ['retiro', Validators.required],
      ese_sucid: [null as number | null],
      ese_dirid: [null as number | null],
      receptor: ['titular', Validators.required],
      ese_nrodoc: [''],
      ese_nombre: [''],
      ese_apelli: [''],
      ese_telef: [''],
      ese_obs: ['', Validators.maxLength(255)],
    });

    this.form.get('modalidad')!.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => this.ajustar());
    this.form.get('receptor')!.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => this.ajustar());
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.fila) {
      this.cfg = null;
      this.entregado = false;
      this.guardando = false;
      this.cargando = true;
      this.cobro = null;
      this.service.entregaConfig(this.fila.sse_id).subscribe({
        next: (res) => {
          this.cfg = res.data;
          this.reiniciar();
          this.cargando = false;
          if (!this.cfg.cobro.habilitado) this.cargarCobro();
        },
        error: () => { this.cargando = false; this.toastr.error('No se pudieron cargar los datos de entrega', 'Error'); },
      });
    }
  }

  private reiniciar(): void {
    const c = this.cfg!;
    this.form.reset({
      modalidad: 'retiro',
      ese_sucid: c.sse_sucid ?? c.sucursales[0]?.suc_id ?? null,
      ese_dirid: c.sse_dirid ?? c.direcciones[0]?.dir_id ?? null,
      receptor: 'titular',
      ese_nrodoc: c.titular.nrodoc ?? '', ese_nombre: '', ese_apelli: '', ese_telef: c.titular.telef ?? '',
      ese_obs: '',
    });
    // Por defecto todo lo recibido se marca como devuelto: el admin desmarca lo que falta.
    this.devueltos = new Set(c.accesorios.map((a) => a.rac_id));
    this.ajustar();
  }

  /** Datos del cobro: solo hacen falta si la entrega está bloqueada por la regla 7. */
  private cargarCobro(): void {
    if (!this.fila) return;
    this.cobros.config().subscribe({ next: (res) => (this.metodos = res.data.metodos) });
    this.cobros.detalle(this.fila.sse_id).subscribe({
      next: (res) => (this.cobro = res.data),
      error: () => (this.cobro = null),   // sin permiso de cobros: queda el bloqueo de siempre
    });
    this.cobroForm.reset({ mpa_codigo: '', nro_transaccion: '', banco: '', observacion: '' });
    this.archivoCobro = null;
    this.errorArchivoCobro = null;
  }

  /** Lo habilita el backend (acciones.cobrar) y que no haya pagos web esperando verificación. */
  get puedeCobrarYEntregar(): boolean {
    const c = this.cobro;
    return !!this.cfg && !this.cfg.cobro.habilitado && !!c?.acciones.cobrar
      && c.resumen.saldo > 0 && c.resumen.en_verificacion === 0;
  }

  get pagosEnVerificacion(): boolean {
    return (this.cobro?.resumen.en_verificacion ?? 0) > 0;
  }

  get mostrarFormulario(): boolean {
    return !!this.cfg && (this.cfg.cobro.habilitado || this.puedeCobrarYEntregar);
  }

  get cobroRequiereReferencia(): boolean {
    const codigo = this.cobroForm?.get('mpa_codigo')?.value;
    return this.metodos.find((m) => m.mpa_codigo === codigo)?.mpa_requcom ?? false;
  }

  cobroInvalido(campo: string): boolean {
    const c = this.cobroForm.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  onArchivoCobro(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;
    this.errorArchivoCobro = null;
    this.archivoCobro = null;

    if (archivo && !['image/jpeg', 'image/png', 'application/pdf'].includes(archivo.type)) {
      this.errorArchivoCobro = 'El comprobante debe ser JPG, PNG o PDF.';
    } else if (archivo && archivo.size > 5 * 1024 * 1024) {
      this.errorArchivoCobro = 'El comprobante no puede superar los 5 MB.';
    } else {
      this.archivoCobro = archivo;
    }
    if (this.errorArchivoCobro) input.value = '';
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }

  get esTercero(): boolean { return this.form.value.receptor === 'tercero'; }
  get esEnvio(): boolean { return this.form.value.modalidad === 'envio'; }
  get faltanAccesorios(): boolean { return !!this.cfg && this.devueltos.size < this.cfg.accesorios.length; }

  private ajustar(): void {
    const set = (campo: string, v: any[]) => { const c = this.form.get(campo)!; c.setValidators(v); c.updateValueAndValidity({ emitEvent: false }); };
    const envio = this.form.get('modalidad')!.value === 'envio';
    const tercero = this.form.get('receptor')!.value === 'tercero';

    set('ese_sucid', envio ? [] : [Validators.required]);
    set('ese_dirid', envio ? [Validators.required] : []);
    // La C.I. del titular solo se pide si el cliente no la tiene registrada.
    set('ese_nrodoc', tercero || !this.cfg?.titular.nrodoc ? [Validators.required, Validators.pattern(/^[0-9]{5,15}$/)] : []);
    set('ese_nombre', tercero ? [Validators.required, Validators.maxLength(100)] : []);
    set('ese_apelli', tercero ? [Validators.required, Validators.maxLength(100)] : []);
  }

  alternarAccesorio(id: number): void {
    this.devueltos.has(id) ? this.devueltos.delete(id) : this.devueltos.add(id);
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.form.invalid || !this.fila) { this.form.markAllAsTouched(); return; }
    if (this.faltanAccesorios && !this.form.value.ese_obs?.trim()) {
      this.form.get('ese_obs')!.setErrors({ backend: 'Indique por qué no se devuelven todos los accesorios.' });
      this.form.get('ese_obs')!.markAsTouched();
      return;
    }

    const v = this.form.getRawValue();

    if (this.puedeCobrarYEntregar) {
      this.cobrarYEntregar(v);
      return;
    }

    this.guardando = true;
    this.service.registrarEntrega(this.fila.sse_id, { ...v, accesorios: [...this.devueltos] }).subscribe({
      next: (res) => {
        this.guardando = false;
        this.entregado = true;
        this.toastr.success(res.message, 'Éxito');
        this.guardado.emit();
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.entries(err.error.errors as Record<string, string[]>).forEach(([c, m]) => {
            const control = this.form.get(c);
            if (control) { control.setErrors({ backend: m[0] }); control.markAsTouched(); }
            else this.toastr.error(m[0], 'Validación');
          });
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar la entrega', 'Error');
      },
    });
  }

  /** Cobro del saldo + entrega en una transacción (si algo falla, no queda ni el cobro). */
  private cobrarYEntregar(entrega: Record<string, unknown>): void {
    if (this.cobroForm.invalid || this.errorArchivoCobro || !this.fila || this.datosFacturacion?.cargando) {
      this.cobroForm.markAllAsTouched();
      return;
    }

    const sseId = this.fila.sse_id;
    const facturacion$ = this.datosFacturacion ? this.datosFacturacion.guardar() : of(null);
    const datos = CobrosServicioService.formData({
      ...entrega,
      accesorios: [...this.devueltos],
      cobro: { ...this.cobroForm.getRawValue(), comprobante: this.archivoCobro },
    });

    this.guardando = true;
    // 1) datos fiscales  2) cobro + entrega (una transacción en el backend)
    facturacion$.pipe(switchMap(() => this.cobros.cobrarYEntregar(sseId, datos))).subscribe({
      next: (res) => {
        this.guardando = false;
        this.entregado = true;
        this.toastr.success(res.message, 'Éxito');
        this.guardado.emit();
      },
      error: (err) => {
        this.guardando = false;
        if (err === ERROR_FACTURACION) return;   // ya marcado junto a los campos fiscales
        if (err.status === 422 && err.error?.errors) {
          Object.entries(err.error.errors as Record<string, string[]>).forEach(([c, m]) => {
            // Los errores del cobro llegan como "cobro.campo".
            const control = c.startsWith('cobro.') ? this.cobroForm.get(c.slice(6)) : this.form.get(c);
            if (control) { control.setErrors({ backend: m[0] }); control.markAsTouched(); }
            else this.toastr.error(m[0], 'Validación');
          });
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar el cobro y la entrega', 'Error');
      },
    });
  }

  errorCobro(campo: string): string | null {
    return this.cobroForm.get(campo)?.errors?.['backend'] ?? null;
  }

  imprimirConstancia(): void {
    if (!this.fila) return;
    this.service.constancia(this.fila.sse_id, 'retiro').subscribe({
      next: (pdf) => this.service.abrirPdf(pdf, `constancia-retiro-${this.fila!.sse_numero}.pdf`),
      error: () => this.toastr.error('No se pudo generar la constancia', 'Error'),
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
