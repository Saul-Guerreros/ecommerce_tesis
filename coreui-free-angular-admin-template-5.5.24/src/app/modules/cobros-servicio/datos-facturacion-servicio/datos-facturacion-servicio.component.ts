import { Component, DestroyRef, Input, OnChanges, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Observable, catchError, of, tap, throwError } from 'rxjs';
import { FormModule, SpinnerModule } from '@coreui/angular';
import { DatoFiscalOpcion, GuardarFacturacion, OpcionesFacturacion } from '../models/factura-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';

/** Error ya mostrado junto a los campos: el contenedor solo corta el flujo. */
export const ERROR_FACTURACION = { facturacion: true } as const;

/**
 * Bloque "Datos de facturación" (CI o RUC) que se carga ANTES del cobro.
 * Lo usan "Registrar cobro" y "Cobrar y entregar" (A5).
 *
 * El contenedor llama a guardar() y, cuando termina bien, registra el cobro:
 *   this.facturacion.guardar().pipe(switchMap(() => cobro$))
 * Si la factura ya se emitió, se muestra en modo lectura y guardar() no hace nada.
 *
 * Las reglas definitivas (dígito verificador, RUC del cliente, canal con su
 * dato) las aplica FacturacionServicioService; los 422 se muestran junto al campo.
 */
@Component({
  selector: 'app-datos-facturacion-servicio',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormModule, SpinnerModule],
  templateUrl: './datos-facturacion-servicio.component.html',
})
export class DatosFacturacionServicioComponent implements OnChanges {
  private readonly destroyRef = inject(DestroyRef);

  @Input() solicitudId: number | null = null;

  opciones: OpcionesFacturacion | null = null;
  cargando = false;
  form: FormGroup;

  private static readonly RUC = /^\d{1,15}-\d$/;

  constructor(private fb: FormBuilder, private service: CobrosServicioService) {
    this.form = this.fb.group({
      tipo_documento: ['CI', Validators.required],
      modo_ruc: ['guardado'],                       // guardado | unico (solo front)
      dfi_id: [null as number | null],
      nrodoc: ['', [Validators.maxLength(20)]],
      raznom: ['', [Validators.maxLength(150)]],
      telefono: ['', [Validators.maxLength(45)]],
      correo: ['', [Validators.email, Validators.maxLength(100)]],
      recibe: ['CORREO', Validators.required],
    });

    // Al elegir un RUC guardado se proponen su teléfono y su correo.
    this.form.get('dfi_id')!.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((id) => {
      const dato = this.datoFiscal(id);
      if (dato) this.form.patchValue({ telefono: dato.dfi_telefono ?? this.form.value.telefono, correo: dato.dfi_correo ?? this.form.value.correo }, { emitEvent: false });
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['solicitudId'] && this.solicitudId) this.cargar();
  }

  cargar(): void {
    if (!this.solicitudId) return;
    this.cargando = true;
    this.service.facturacion(this.solicitudId).subscribe({
      next: (res) => { this.opciones = res.data; this.reiniciar(); this.cargando = false; },
      error: () => { this.opciones = null; this.cargando = false; },
    });
  }

  private reiniciar(): void {
    const o = this.opciones!;
    const a = o.actual;
    const hayGuardados = o.datos_fiscales.length > 0;

    this.form.reset({
      tipo_documento: a?.tipo_documento ?? 'CI',
      modo_ruc: a ? (a.dfi_id ? 'guardado' : (a.tipo_documento === 'RUC' ? 'unico' : (hayGuardados ? 'guardado' : 'unico'))) : (hayGuardados ? 'guardado' : 'unico'),
      dfi_id: a?.dfi_id ?? o.datos_fiscales.find((d) => d.dfi_principal)?.dfi_id ?? null,
      nrodoc: a && !a.dfi_id && a.tipo_documento === 'RUC' ? a.nrodoc : '',
      raznom: a && !a.dfi_id && a.tipo_documento === 'RUC' ? a.raznom : '',
      telefono: a?.telefono ?? o.titular.telefono ?? '',
      correo: a?.correo ?? o.titular.correo ?? '',
      recibe: a?.recibe ?? (o.titular.correo ? 'CORREO' : 'WHATSAPP'),
    }, { emitEvent: false });

    if (!o.editable) this.form.disable({ emitEvent: false });
    else this.form.enable({ emitEvent: false });
  }

  // ---------- Vista ----------

  get esRuc(): boolean { return this.form.get('tipo_documento')?.value === 'RUC'; }
  get rucGuardado(): boolean { return this.esRuc && this.form.get('modo_ruc')?.value === 'guardado'; }
  get rucUnico(): boolean { return this.esRuc && this.form.get('modo_ruc')?.value === 'unico'; }
  get editable(): boolean { return !!this.opciones?.editable; }

  datoFiscal(id: number | null): DatoFiscalOpcion | undefined {
    return this.opciones?.datos_fiscales.find((d) => d.dfi_id === Number(id));
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  error(campo: string): string | null {
    const e = this.form.get(campo)?.errors;
    if (!e) return null;
    return e['backend'] ?? e['local'] ?? (e['email'] ? 'El correo no es válido.' : null);
  }

  // ---------- Guardar ----------

  /** Guarda la elección. Emite ERROR_FACTURACION si hay errores (ya mostrados en los campos). */
  guardar(): Observable<unknown> {
    if (!this.solicitudId || !this.opciones || !this.opciones.editable) return of(null);

    if (!this.validarLocal()) return throwError(() => ERROR_FACTURACION);

    return this.service.guardarFacturacion(this.solicitudId, this.payload()).pipe(
      tap((res) => (this.opciones = res.data)),
      catchError((err) => {
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (err?.status !== 422 || !errores) return throwError(() => err);

        Object.entries(errores).forEach(([campo, m]) => {
          // dfi_id / nrodoc: el error del RUC se muestra en el campo visible.
          const control = this.form.get(campo) ?? this.form.get('tipo_documento');
          control?.setErrors({ backend: m[0] });
          control?.markAsTouched();
        });
        return throwError(() => ERROR_FACTURACION);
      }),
    );
  }

  private validarLocal(): boolean {
    const v = this.form.getRawValue();
    const marcar = (campo: string, mensaje: string) => {
      this.form.get(campo)!.setErrors({ local: mensaje });
      this.form.get(campo)!.markAsTouched();
    };

    // Recalcula los validadores: limpia errores locales/backend de un intento
    // anterior (p. ej. de un RUC que ya no se usa porque se eligió la CI).
    Object.values(this.form.controls).forEach((c) => c.updateValueAndValidity({ emitEvent: false }));
    this.form.markAllAsTouched();
    let ok = this.form.valid;

    if (this.rucGuardado && !v.dfi_id) { marcar('dfi_id', 'Elija un RUC guardado.'); ok = false; }
    if (this.rucUnico && !DatosFacturacionServicioComponent.RUC.test((v.nrodoc ?? '').trim())) {
      marcar('nrodoc', 'Formato 80069563-1 (número, guion y dígito verificador).'); ok = false;
    }
    if (this.rucUnico && !(v.raznom ?? '').trim()) { marcar('raznom', 'Ingrese la razón social.'); ok = false; }
    if (v.recibe === 'CORREO' && !(v.correo ?? '').trim()) { marcar('correo', 'Ingrese el correo para enviar la factura.'); ok = false; }
    if (v.recibe === 'WHATSAPP' && !(v.telefono ?? '').trim()) { marcar('telefono', 'Ingrese el celular para enviar la factura.'); ok = false; }

    return ok;
  }

  private payload(): GuardarFacturacion {
    const v = this.form.getRawValue();
    const base: GuardarFacturacion = {
      tipo_documento: v.tipo_documento,
      recibe: v.recibe,
      telefono: (v.telefono ?? '').trim() || null,
      correo: (v.correo ?? '').trim() || null,
    };

    if (this.rucGuardado) return { ...base, dfi_id: Number(v.dfi_id) };
    if (this.rucUnico) return { ...base, nrodoc: v.nrodoc.trim(), raznom: v.raznom.trim() };
    return base;
  }
}
