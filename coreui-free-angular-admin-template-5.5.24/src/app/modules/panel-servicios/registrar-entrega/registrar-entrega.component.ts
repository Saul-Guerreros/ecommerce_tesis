import { Component, DestroyRef, EventEmitter, Input, OnChanges, Output, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { EntregaConfig, PanelFila } from '../models/panel-servicio.model';
import { PanelServiciosService } from '../service/panel-servicios.service';

/**
 * A5 - Registrar entrega. Receptor (titular o tercero con C.I.), checklist
 * de accesorios devueltos y, al terminar, la constancia de retiro.
 */
@Component({
  selector: 'app-registrar-entrega',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './registrar-entrega.component.html',
})
export class RegistrarEntregaComponent implements OnChanges {
  private readonly destroyRef = inject(DestroyRef);

  @Input() visible = false;
  @Input() fila: PanelFila | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  cfg: EntregaConfig | null = null;
  form: FormGroup;
  cargando = false;
  guardando = false;
  entregado = false;
  devueltos = new Set<number>();

  constructor(private fb: FormBuilder, private service: PanelServiciosService, private toastr: ToastrService) {
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
      this.service.entregaConfig(this.fila.sse_id).subscribe({
        next: (res) => { this.cfg = res.data; this.reiniciar(); this.cargando = false; },
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
