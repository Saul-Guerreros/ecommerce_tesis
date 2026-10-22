import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { DatosGarantiaPayload, EmpresaOpcion, OrdenConfig, OrdenTrabajo, ResultadoGarantia } from '../models/orden-tecnico.model';
import { OrdenesTecnicoService } from '../service/ordenes-tecnico.service';

type Accion = 'iniciar' | 'guardar' | 'enviar' | 'resolver';

/**
 * Garantía de COMPRA: la cubre la empresa responsable del producto (distribuidora o
 * fabricante). El técnico no diagnostica ni presupuesta: gestiona el reclamo con esa
 * empresa, registra el envío y el regreso, y al cerrar se avisa al cliente.
 */
@Component({
  selector: 'app-gestion-garantia',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule],
  templateUrl: './gestion-garantia.component.html',
})
export class GestionGarantiaComponent implements OnChanges {
  @Input() orden!: OrdenTrabajo;
  @Input() config: OrdenConfig | null = null;
  @Output() actualizada = new EventEmitter<OrdenTrabajo>();

  readonly RESULTADOS: { valor: ResultadoGarantia; texto: string }[] = [
    { valor: 'REPARADO', texto: 'Reparado por la empresa' },
    { valor: 'REEMPLAZADO', texto: 'Reemplazado por otro del mismo modelo' },
    { valor: 'RECHAZADO', texto: 'Garantía no aceptada' },
  ];

  datos: FormGroup;
  envio: FormGroup;
  resultado: FormGroup;

  procesando: Accion | null = null;
  /** GESTION_GARANTIA: la empresa no acepta el reclamo y el producto vuelve sin enviarse. */
  cerrandoSinEnvio = false;

  constructor(private fb: FormBuilder, private service: OrdenesTecnicoService, private toastr: ToastrService) {
    this.datos = this.fb.group({
      gpr_proid: [null as number | null],
      gpr_nrocaso: ['', Validators.maxLength(50)],
      gpr_contacto: ['', Validators.maxLength(150)],
      gpr_obs: ['', Validators.maxLength(500)],
    });
    this.envio = this.fb.group({ gpr_fecenv: [this.hoy(), Validators.required] });
    this.resultado = this.fb.group({
      gpr_result: [null as ResultadoGarantia | null, Validators.required],
      gpr_fecrec: [this.hoy()],
      gpr_nroserr: ['', Validators.maxLength(80)],
      gpr_obs: ['', Validators.maxLength(500)],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (!changes['orden']) return;

    const g = this.orden?.garantia_empresa?.gestion;
    this.datos.reset({
      gpr_proid: g?.gpr_proid ?? null,
      gpr_nrocaso: g?.gpr_nrocaso ?? '',
      gpr_contacto: g?.gpr_contacto ?? '',
      gpr_obs: g?.gpr_obs ?? '',
    });
    // Una vez enviado, la empresa ya no se cambia: el reclamo está en sus manos.
    if (this.estado === 'EN_EMPRESA') this.datos.get('gpr_proid')!.disable();
    else this.datos.get('gpr_proid')!.enable();

    this.resultado.reset({ gpr_result: null, gpr_fecrec: this.hoy(), gpr_nroserr: '', gpr_obs: '' });
    this.cerrandoSinEnvio = false;
  }

  get estado(): string {
    return this.orden?.estado.codigo;
  }

  get ge() {
    return this.orden?.garantia_empresa ?? null;
  }

  get empresaSel(): EmpresaOpcion | null {
    const id = this.datos.getRawValue().gpr_proid;
    return this.config?.proveedores.find((e) => e.pro_id === id) ?? null;
  }

  get esReemplazo(): boolean {
    return this.resultado.get('gpr_result')?.value === 'REEMPLAZADO';
  }

  get esRechazo(): boolean {
    return this.cerrandoSinEnvio || this.resultado.get('gpr_result')?.value === 'RECHAZADO';
  }

  error(form: FormGroup, campo: string): string | null {
    return form.get(campo)?.errors?.['backend'] ?? null;
  }

  // ---------- Acciones ----------

  iniciar(): void {
    this.ejecutar('iniciar', this.service.iniciarGarantia(this.orden.sse_id));
  }

  guardar(): void {
    if (this.datos.invalid) return;
    this.ejecutar('guardar', this.service.guardarGarantia(this.orden.sse_id, this.payloadDatos()));
  }

  enviar(): void {
    if (this.datos.invalid || this.envio.invalid) {
      this.envio.markAllAsTouched();
      return;
    }
    this.ejecutar('enviar', this.service.enviarGarantia(this.orden.sse_id, {
      ...this.payloadDatos(),
      gpr_fecenv: this.envio.value.gpr_fecenv,
    }));
  }

  resolver(): void {
    const r = this.resultado.getRawValue();
    const resultado: ResultadoGarantia | null = this.cerrandoSinEnvio ? 'RECHAZADO' : r.gpr_result;

    if (!resultado) {
      this.resultado.get('gpr_result')!.setErrors({ backend: 'Indique el resultado de la gestión.' });
      return;
    }
    if (resultado === 'RECHAZADO' && !String(r.gpr_obs ?? '').trim()) {
      this.resultado.get('gpr_obs')!.setErrors({ backend: 'Indique el motivo: lo verá el cliente.' });
      return;
    }

    this.ejecutar('resolver', this.service.resolverGarantia(this.orden.sse_id, {
      gpr_result: resultado,
      gpr_fecrec: this.cerrandoSinEnvio ? null : (r.gpr_fecrec || null),
      gpr_nroserr: resultado === 'REEMPLAZADO' ? (r.gpr_nroserr || null) : null,
      gpr_obs: r.gpr_obs || null,
    }));
  }

  // ---------- Auxiliares ----------

  private payloadDatos(): DatosGarantiaPayload {
    const d = this.datos.getRawValue();
    return {
      gpr_proid: d.gpr_proid ?? null,
      gpr_nrocaso: d.gpr_nrocaso || null,
      gpr_contacto: d.gpr_contacto || null,
      gpr_obs: d.gpr_obs || null,
    };
  }

  private ejecutar(accion: Accion, peticion: ReturnType<OrdenesTecnicoService['iniciarGarantia']>): void {
    this.procesando = accion;
    peticion.subscribe({
      next: (res) => {
        this.procesando = null;
        this.toastr.success(res.message, 'Éxito');
        this.actualizada.emit(res.data);
      },
      error: (err) => {
        this.procesando = null;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (errores) {
          Object.entries(errores).forEach(([campo, msgs]) => {
            [this.datos, this.envio, this.resultado].forEach((f) => f.get(campo)?.setErrors({ backend: msgs[0] }));
          });
        }
        const msg = errores ? Object.values(errores)[0]?.[0] : null;
        this.toastr.error(msg ?? err?.error?.message ?? 'No se pudo completar la acción', 'Error');
      },
    });
  }

  /** Fecha de hoy (yyyy-MM-dd) en hora local: toISOString() usaría UTC y podría adelantar el día. */
  private hoy(): string {
    const d = new Date();
    const dos = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`;
  }
}
