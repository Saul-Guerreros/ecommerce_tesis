import { Component, DestroyRef, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { of, switchMap } from 'rxjs';
import { AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { CobroDetalle, MetodoPagoOpcion } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';
import { DatosFacturacionServicioComponent, ERROR_FACTURACION } from '../datos-facturacion-servicio/datos-facturacion-servicio.component';

/**
 * Cobro presencial en el mostrador: ANTICIPO (antes de LISTA, opcional y
 * sin mínimo) o SALDO / TOTAL (en LISTA). Queda CONFIRMADO en el acto y
 * guarda quién cobró y en qué sucursal (lo hace el backend).
 *
 * La referencia es obligatoria para transferencia y giro Tigo (la exige
 * también CobroMostradorService); el comprobante es opcional.
 *
 * Facturación: en LISTA se cargan antes los datos fiscales (CI o RUC). Se
 * guardan primero (PUT .../facturacion) y recién después se registra el
 * cobro: si este completa el total, el backend emite la factura con esos datos.
 */
@Component({
  selector: 'app-registrar-cobro',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ModalModule, ButtonModule, FormModule, AlertModule, SpinnerModule, DatosFacturacionServicioComponent],
  templateUrl: './registrar-cobro.component.html',
})
export class RegistrarCobroComponent implements OnInit, OnChanges {
  private readonly destroyRef = inject(DestroyRef);

  @Input() visible = false;
  @Input() detalle: CobroDetalle | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<CobroDetalle>();

  /** Solo existe en LISTA (el anticipo nunca factura). */
  @ViewChild(DatosFacturacionServicioComponent) datosFacturacion?: DatosFacturacionServicioComponent;

  metodos: MetodoPagoOpcion[] = [];
  form: FormGroup;
  archivo: File | null = null;
  errorArchivo: string | null = null;
  guardando = false;

  constructor(private fb: FormBuilder, private service: CobrosServicioService, private toastr: ToastrService) {
    this.form = this.fb.group({
      mpa_codigo: ['', Validators.required],
      monto: [null as number | null, [Validators.required, Validators.min(1)]],
      nro_transaccion: ['', Validators.maxLength(50)],
      banco: ['', Validators.maxLength(60)],
      fecha_pago: [''],
      observacion: ['', Validators.maxLength(255)],
    });

    // La referencia pasa a ser obligatoria cuando el método lleva comprobante.
    this.form.get('mpa_codigo')!.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      const ref = this.form.get('nro_transaccion')!;
      ref.setValidators(this.requiereReferencia ? [Validators.required, Validators.maxLength(50)] : [Validators.maxLength(50)]);
      ref.updateValueAndValidity({ emitEvent: false });
    });
  }

  ngOnInit(): void {
    this.service.config().subscribe({ next: (res) => (this.metodos = res.data.metodos) });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.detalle) {
      this.archivo = null;
      this.errorArchivo = null;
      this.form.reset({
        mpa_codigo: '',
        // En LISTA se propone el saldo; el anticipo arranca vacío (no hay mínimo).
        monto: this.esAnticipo ? null : this.detalle.resumen.saldo,
        nro_transaccion: '',
        banco: '',
       fecha_pago: this.hoyLocal(),
        observacion: '',
      });
    }
  }

	/** Fecha local (Paraguay) en formato YYYY-MM-DD; toISOString() da la fecha UTC. */
	private hoyLocal(): string {
	  const d = new Date();
	  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
	}
	
  get esAnticipo(): boolean { return !this.detalle?.resumen.consolidado; }

  get requiereReferencia(): boolean {
    const codigo = this.form?.get('mpa_codigo')?.value;
    return this.metodos.find((m) => m.mpa_codigo === codigo)?.mpa_requcom ?? false;
  }

  get esGiro(): boolean { return this.form.get('mpa_codigo')?.value === 'BILLETERA_MOVIL'; }

  /** Tope que el front muestra; el definitivo lo aplica el backend. */
  get excedeSaldo(): boolean {
    if (this.esAnticipo || !this.detalle) return false;
    return Number(this.form.get('monto')?.value ?? 0) > this.detalle.resumen.saldo;
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  onArchivo(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;
    this.errorArchivo = null;
    this.archivo = null;

    if (archivo && !['image/jpeg', 'image/png', 'application/pdf'].includes(archivo.type)) {
      this.errorArchivo = 'El comprobante debe ser JPG, PNG o PDF.';
    } else if (archivo && archivo.size > 5 * 1024 * 1024) {
      this.errorArchivo = 'El comprobante no puede superar los 5 MB.';
    } else {
      this.archivo = archivo;
    }
    if (this.errorArchivo) input.value = '';
  }

  guardar(): void {
    if (this.guardando) return;
    if (!this.detalle || this.form.invalid || this.excedeSaldo || this.errorArchivo || this.datosFacturacion?.cargando) {
      this.form.markAllAsTouched();
      return;
    }

    const sseId = this.detalle.sse_id;
    const datos = CobrosServicioService.formData({ ...this.form.getRawValue(), comprobante: this.archivo });
    const facturacion$ = this.datosFacturacion ? this.datosFacturacion.guardar() : of(null);
    this.guardando = true;

    // 1) datos fiscales  2) cobro. Si el 1 falla, no se cobra.
    facturacion$.pipe(switchMap(() => this.service.registrarCobro(sseId, datos))).subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message, this.esAnticipo ? 'Anticipo registrado' : 'Cobro registrado');
        this.guardado.emit(res.data);
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err === ERROR_FACTURACION) return;   // ya marcado junto a los campos fiscales
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (err?.status === 422 && errores) {
          Object.entries(errores).forEach(([campo, m]) => {
            const control = this.form.get(campo);
            if (control) { control.setErrors({ backend: m[0] }); control.markAsTouched(); }
            else this.toastr.error(m[0], 'Validación');
          });
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar el cobro', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}
