import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { CancelacionConfig, PanelFila } from '../models/panel-servicio.model';
import { PanelServiciosService } from '../service/panel-servicios.service';

/**
 * Cancelar una solicitud hasta antes de LISTA. Si el equipo está en el
 * taller, registra su devolución (sin cobro) en el mismo acto. Los bloqueos
 * (anticipo, tránsito, factura) los informa el backend.
 */
@Component({
  selector: 'app-cancelar-solicitud',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './cancelar-solicitud.component.html',
})
export class CancelarSolicitudComponent implements OnChanges {
  @Input() visible = false;
  @Input() fila: PanelFila | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  cfg: CancelacionConfig | null = null;
  cargando = false;
  guardando = false;
  cancelada = false;
  devueltos = new Set<number>();
  form: FormGroup;

  constructor(private fb: FormBuilder, private service: PanelServiciosService, private toastr: ToastrService) {
    this.form = this.fb.group({
      motivo: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(255)]],
      modalidad: ['retiro'],
      ese_sucid: [null as number | null],
      ese_dirid: [null as number | null],
      receptor: ['titular'],
      ese_nrodoc: [''],
      ese_nombre: [''],
      ese_apelli: [''],
      ese_telef: [''],
      ese_obs: ['', Validators.maxLength(255)],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.fila) this.cargar();
  }

  private cargar(): void {
    this.cfg = null;
    this.cancelada = false;
    this.cargando = true;
    this.service.cancelacionConfig(this.fila!.sse_id).subscribe({
      next: (res) => {
        this.cargando = false;
        this.cfg = res.data;
        const dev = res.data.devolucion;
        this.devueltos = new Set(dev?.accesorios.map((a) => a.rac_id) ?? []);
        this.form.reset({
          motivo: '', modalidad: 'retiro', receptor: 'titular',
          ese_sucid: dev?.sucursales[0]?.suc_id ?? null, ese_dirid: dev?.direcciones[0]?.dir_id ?? null,
          ese_nrodoc: '', ese_nombre: '', ese_apelli: '', ese_telef: dev?.titular.telef ?? '', ese_obs: '',
        });
      },
      error: (err) => {
        this.cargando = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo cargar la cancelación', 'Error');
        this.cerrar();
      },
    });
  }

  get devolucion() { return this.cfg?.devolucion ?? null; }
  get esEnvio(): boolean { return this.form.value.modalidad === 'envio'; }
  get esTercero(): boolean { return this.form.value.receptor === 'tercero'; }
  get faltanAccesorios(): boolean {
    return (this.devolucion?.accesorios ?? []).some((a) => !this.devueltos.has(a.rac_id));
  }

  alternarAccesorio(id: number): void {
    this.devueltos.has(id) ? this.devueltos.delete(id) : this.devueltos.add(id);
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  /** Validación local de la devolución (la definitiva, en el backend). */
  private devolucionValida(): boolean {
    if (!this.devolucion) return true;
    const v = this.form.value;
    let ok = true;
    const exigir = (campo: string, cond: boolean) => {
      if (cond) { this.form.get(campo)!.setErrors({ backend: 'Obligatorio.' }); this.form.get(campo)!.markAsTouched(); ok = false; }
    };
    exigir('ese_sucid', !this.esEnvio && !v.ese_sucid);
    exigir('ese_dirid', this.esEnvio && !v.ese_dirid);
    exigir('ese_nrodoc', (this.esTercero || !this.devolucion.titular.nrodoc) && !/^\d{5,15}$/.test(v.ese_nrodoc ?? ''));
    exigir('ese_nombre', this.esTercero && !v.ese_nombre?.trim());
    exigir('ese_apelli', this.esTercero && !v.ese_apelli?.trim());
    exigir('ese_obs', this.faltanAccesorios && !v.ese_obs?.trim());
    return ok;
  }

  guardar(): void {
    if (!this.fila || !this.cfg?.cancelable || this.guardando) return;
    this.form.get('motivo')!.markAsTouched();
    if (this.form.get('motivo')!.invalid || !this.devolucionValida()) return;

    const v = this.form.getRawValue();
    const datos: Record<string, unknown> = { motivo: v.motivo.trim() };

    if (this.devolucion) {
      datos['devolucion'] = {
        modalidad: v.modalidad,
        ese_sucid: this.esEnvio ? null : v.ese_sucid,
        ese_dirid: this.esEnvio ? v.ese_dirid : null,
        receptor: v.receptor,
        ese_nrodoc: v.ese_nrodoc || null,
        ese_nombre: this.esTercero ? v.ese_nombre : null,
        ese_apelli: this.esTercero ? v.ese_apelli : null,
        ese_telef: v.ese_telef || null,
        accesorios: [...this.devueltos],
        ese_obs: v.ese_obs || null,
      };
    }

    this.guardando = true;
    this.service.cancelar(this.fila.sse_id, datos).subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message, 'Solicitud cancelada');
        this.guardado.emit();
        if (this.devolucion) this.cancelada = true;   // queda para imprimir la constancia
        else this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (err?.status === 422 && errores) {
          Object.entries(errores).forEach(([k, m]) => {
            const control = this.form.get(k.replace('devolucion.', ''));
            if (control) { control.setErrors({ backend: m[0] }); control.markAsTouched(); }
            else this.toastr.error(m[0], 'Validación');
          });
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo cancelar la solicitud', 'Error');
      },
    });
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