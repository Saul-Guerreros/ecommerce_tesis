import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { CobroDetalle, PagoServicio } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';

/**
 * Registrar el cobro de UN pago contra su factura (anticipo o final).
 * Lo habilita el backend (pago.puede_cobrar): factura emitida con su
 * documento, comprobante web validado y, en efectivo o mostrador, un cajero
 * de la sucursal del equipo.
 *
 * Lo recibido va en monto_verificado: de menos no se acepta; de más hay
 * que confirmar el excedente. En transferencias de mostrador la referencia
 * es obligatoria y el comprobante, opcional.
 */
@Component({
  selector: 'app-registrar-cobro',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ModalModule, ButtonModule, FormModule, AlertModule, SpinnerModule],
  templateUrl: './registrar-cobro.component.html',
})
export class RegistrarCobroComponent implements OnChanges {
  @Input() visible = false;
  @Input() detalle: CobroDetalle | null = null;
  @Input() pago: PagoServicio | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<CobroDetalle>();

  form: FormGroup;
  archivo: File | null = null;
  errorArchivo: string | null = null;
  guardando = false;

  constructor(private fb: FormBuilder, private service: CobrosServicioService, private toastr: ToastrService) {
    this.form = this.fb.group({
      monto_verificado: [null as number | null, [Validators.required, Validators.min(1)]],
      confirmar_diferencia: [false],
      nro_transaccion: ['', Validators.maxLength(50)],
      banco: ['', Validators.maxLength(60)],
      fecha_pago: [''],
      observacion: ['', Validators.maxLength(255)],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.pago) {
      this.archivo = null;
      this.errorArchivo = null;
      this.form.reset({
        monto_verificado: this.pago.monto_asignado,
        confirmar_diferencia: false,
        nro_transaccion: '',
        banco: '',
        fecha_pago: this.hoyLocal(),
        observacion: '',
      });

      const ref = this.form.get('nro_transaccion')!;
      ref.setValidators(this.pideReferencia ? [Validators.required, Validators.maxLength(50)] : [Validators.maxLength(50)]);
      ref.updateValueAndValidity({ emitEvent: false });
    }
  }

  /** Fecha local (Paraguay) YYYY-MM-DD; toISOString() da la fecha UTC. */
  private hoyLocal(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  get esMostrador(): boolean { return this.pago?.canal === 'MOSTRADOR'; }

  /** Transferencia o giro registrado en el mostrador: sin referencia no se rastrea el dinero. */
  get pideReferencia(): boolean { return !!this.pago?.metodo.requcom && this.esMostrador; }

  get esGiro(): boolean { return this.pago?.metodo.codigo === 'BILLETERA_MOVIL'; }

  get monto(): number { return Number(this.form.get('monto_verificado')?.value ?? 0); }
  get menor(): boolean { return !!this.pago && this.monto > 0 && this.monto < this.pago.monto_asignado; }
  get mayor(): boolean { return !!this.pago && this.monto > this.pago.monto_asignado; }

  /** Factura contra la que se cobra (anticipo o final). */
  get factura() {
    return this.pago?.tipo === 'ANTICIPO' ? this.detalle?.facturas.anticipo : this.detalle?.facturas.final;
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
    if (this.guardando || !this.detalle || !this.pago) return;
    if (this.form.invalid || this.menor || this.errorArchivo || (this.mayor && !this.form.value.confirmar_diferencia)) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    const datos = CobrosServicioService.formData({
      ...v,
      confirmar_diferencia: this.mayor ? true : null,
      comprobante: this.esMostrador ? this.archivo : null,
    });

    this.guardando = true;
    this.service.registrarCobro(this.detalle.sse_id, this.pago.spa_id, datos).subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message, 'Cobro registrado');
        this.guardado.emit(res.data);
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
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