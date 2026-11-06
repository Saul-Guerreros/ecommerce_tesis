import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, DestroyRef, OnDestroy, OnInit, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Direccion } from '../../../core/models/direccion.model';
import { ServicioTecnicoService } from '../../../core/services/servicio-tecnico.service';
import { FormDireccionComponent } from '../../checkout/form-direccion/form-direccion.component';
import { hoyIso, proximoDiaHabilIso, sinDomingoValidator } from '../../../shared/validators/fecha-servicio';
import { comprimirImagen } from '../../../shared/utils/comprimir-imagen';
import { minLengthSinEspacios, requeridoSinEspacios } from '../../../shared/validators/texto';
import { ConfigServicio, DiagnosticoIa, ModalidadIngreso } from '../../../core/models/servicio-tecnico.model';
import { DiagnosticoIaComponent } from '../diagnostico-ia/diagnostico-ia.component';
interface Foto {
  archivo: File;
  preview: string;
}

type Paso = 1 | 2 | 3;

/**
 * C2 - Wizard de solicitud (Fase 0): 1 Equipo → 2 Ingreso → 3 Confirmación.
 * Un FormGroup por paso: "Siguiente" valida solo el grupo del paso actual.
 */
@Component({
  selector: 'app-solicitud-servicio',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, ReactiveFormsModule, RouterModule, FormDireccionComponent, DiagnosticoIaComponent],
  templateUrl: './solicitud-servicio.component.html',
  styleUrl: './solicitud-servicio.component.css',
})
export class SolicitudServicioComponent implements OnInit, OnDestroy {
  private readonly destroyRef = inject(DestroyRef);

  readonly MAX_MB = 5;
  readonly hoy = hoyIso();
  readonly MAX_ORIGINAL_MB = 25;   // tope de la foto original, antes de comprimir
  procesandoFotos = false;

  config: ConfigServicio | null = null;
  paso: Paso = 1;
  cargando = true;
  enviando = false;

  form!: FormGroup;
  fotos: Foto[] = [];
  errorFotos: string | null = null;

  /** Paso 6: número de la solicitud original cuando es un reclamo de garantía. */
  reclamoDe: string | null = null;

  costoRetiro: number | null = null;
  diagnosticoIa: DiagnosticoIa | null = null;
  consultandoIa = false;
  private readonly camposIa = ['sse_tdiid', 'sse_marca', 'sse_modelo', 'sse_proble'];
  calculandoCosto = false;
  modalDireccion = false;

  constructor(
    private fb: FormBuilder,
    private service: ServicioTecnicoService,
    private toastr: ToastrService,
    private router: Router,
    private route: ActivatedRoute,
  ) {}

  ngOnInit(): void {
    this.construirFormulario();
    const reclamo = this.route.snapshot.queryParamMap.get('reclamo');

    this.service.config().subscribe({
      next: (cfg) => {
        this.config = cfg;
        if (reclamo) {
          this.prepararReclamo(reclamo);
        } else {
          this.cargando = false;
        }
      },
      error: (err) => {
        this.cargando = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo cargar el formulario', 'Servicio técnico');
      },
    });
  }

  ngOnDestroy(): void {
    this.fotos.forEach((f) => URL.revokeObjectURL(f.preview));
  }

  // ---------- Formulario ----------

  private construirFormulario(): void {
    this.form = this.fb.group({
      equipo: this.fb.group({
        sse_garant: [false],
        sse_dpdid: [null as number | null],   // línea de compra (producto) que cubre la garantía
        sse_tdiid: [null as number | null],   // Punto 7: opcional, la IA lo determina
        sse_marca: ['', [requeridoSinEspacios, Validators.maxLength(50)]],
        sse_modelo: ['', [requeridoSinEspacios, Validators.maxLength(80)]],
        sse_nroser: ['', Validators.maxLength(80)],
        sse_proble: ['', [Validators.required, minLengthSinEspacios(10), Validators.maxLength(500)]],
        sse_acceso: ['', Validators.maxLength(255)],
      }),
      ingreso: this.fb.group({
        modalidad: ['sucursal' as ModalidadIngreso, Validators.required],
        sse_sucid: [null as number | null, Validators.required],
        sse_dirid: [null as number | null],
        sse_fecing: [proximoDiaHabilIso(), [Validators.required, sinDomingoValidator]],
      }),
      confirmacion: this.fb.group({
        acepta_condiciones: [false, Validators.requiredTrue],
      }),
    });

    this.equipo.get('sse_garant')!.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((garantia: boolean) => {
        const linea = this.equipo.get('sse_dpdid')!;
        linea.setValidators(garantia ? [Validators.required] : []);
        if (!garantia) linea.setValue(null);
        linea.updateValueAndValidity();
      });

    this.ingreso.get('modalidad')!.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((m: ModalidadIngreso) => {
        this.aplicar('sse_sucid', m === 'sucursal');
        this.aplicar('sse_dirid', m === 'domicilio');
        this.costoRetiro = null;
        if (m === 'domicilio' && this.ingreso.value.sse_dirid) this.calcularCosto(this.ingreso.value.sse_dirid);
      });

    // Si cambia el equipo o el problema, el diagnóstico IA deja de corresponder a lo que se envía.
    [...this.camposIa, 'sse_garant'].forEach((c) =>
      this.equipo.get(c)!.valueChanges
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe(() => (this.diagnosticoIa = null)));
  }

  private aplicar(campo: string, requerido: boolean): void {
    const c = this.ingreso.get(campo)!;
    c.setValidators(requerido ? [Validators.required] : []);
    c.updateValueAndValidity();
  }

  get equipo(): FormGroup { return this.form.get('equipo') as FormGroup; }
  get ingreso(): FormGroup { return this.form.get('ingreso') as FormGroup; }
  get confirmacion(): FormGroup { return this.form.get('confirmacion') as FormGroup; }

  get esGarantia(): boolean { return !!this.equipo.value.sse_garant; }
  get esDomicilio(): boolean { return this.ingreso.value.modalidad === 'domicilio'; }

  invalido(grupo: FormGroup, campo: string): boolean {
    const c = grupo.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(grupo: FormGroup, campo: string): string | null {
    return grupo.get(campo)?.errors?.['backend'] ?? null;
  }

  /**
   * Reclamo de garantía: el equipo es el mismo del servicio original, así que
   * sus datos se precargan y se bloquean (el backend igual los toma de la
   * solicitud original). No se elige pedido.
   */
  private prepararReclamo(numero: string): void {
    this.service.detalle(numero).subscribe({
      next: (res) => {
        const s = res.data;
        if (!s.garantia?.puede_reclamar || !s.equipo_datos) {
          this.toastr.warning('Ese servicio no tiene una garantía vigente para reclamar.', 'Servicio técnico');
          this.cargando = false;
          return;
        }
        this.reclamoDe = numero;
        this.equipo.patchValue({
          sse_garant: true,
          sse_tdiid: s.equipo_datos.tdi_id,
          sse_marca: s.equipo_datos.marca,
          sse_modelo: s.equipo_datos.modelo,
          sse_nroser: s.equipo_datos.nroser ?? '',
        });
        // Sin compra: el respaldo de la garantía es el servicio original.
        const linea = this.equipo.get('sse_dpdid')!;
        linea.clearValidators();
        linea.updateValueAndValidity();
        ['sse_tdiid', 'sse_marca', 'sse_modelo', 'sse_nroser'].forEach((c) => this.equipo.get(c)!.disable());
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No encontramos el servicio a reclamar.', 'Servicio técnico');
        this.cargando = false;
      },
    });
  }

  // ---------- Paso 1 ----------

  elegirTipoServicio(garantia: boolean): void {
    this.equipo.patchValue({ sse_garant: garantia });
  }

  async onFotos(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const archivos = Array.from(input.files ?? []);
    input.value = '';   // permite volver a elegir la misma foto
    const max = this.config?.max_fotos ?? 5;
    this.errorFotos = null;
    this.procesandoFotos = true;

    try {
      for (const original of archivos) {
        if (this.fotos.length >= max) { this.errorFotos = `Podés adjuntar hasta ${max} fotos.`; break; }
        if (!/^image\/(jpeg|png|webp)$/.test(original.type)) { this.errorFotos = `"${original.name}" no es JPG, PNG o WEBP.`; continue; }
        if (original.size > this.MAX_ORIGINAL_MB * 1024 * 1024) { this.errorFotos = `"${original.name}" supera los ${this.MAX_ORIGINAL_MB} MB.`; continue; }

        try {
          const archivo = await comprimirImagen(original, { maxBytes: (this.MAX_MB - 0.5) * 1024 * 1024 });
          if (archivo.size > this.MAX_MB * 1024 * 1024) { this.errorFotos = `"${original.name}" sigue superando los ${this.MAX_MB} MB.`; continue; }
          this.fotos.push({ archivo, preview: URL.createObjectURL(archivo) });
        } catch {
          this.errorFotos = `No se pudo procesar "${original.name}". Probá con otra foto.`;
        }
      }
    } finally {
      this.procesandoFotos = false;
    }
  }

  quitarFoto(i: number): void {
    URL.revokeObjectURL(this.fotos[i].preview);
    this.fotos.splice(i, 1);
  }

    consultarIa(): void {
    if (this.consultandoIa) return;

    // Los campos bloqueados del reclamo cuentan como completos.
    const listos = this.camposIa.every((c) => {
      const ctrl = this.equipo.get(c)!;
      return ctrl.disabled || ctrl.valid;
    });
    if (!listos) {
      this.camposIa.forEach((c) => this.equipo.get(c)!.markAsTouched());
      this.toastr.warning('Completá la marca, el modelo y el problema.', 'Diagnóstico preliminar');
      return;
    }

    const e = this.equipo.getRawValue();
    this.consultandoIa = true;
    this.service.diagnosticoPreliminar({
      sse_tdiid: e.sse_tdiid ?? null,
      sse_marca: e.sse_marca.trim(),
      sse_modelo: e.sse_modelo.trim(),
      sse_proble: e.sse_proble.trim(),
      sse_garant: !!e.sse_garant || !!this.reclamoDe,
    }).subscribe({
      next: (res) => {
        this.aplicarTipoDetectado(res.data);
        this.diagnosticoIa = res.data;
        this.consultandoIa = false;
      },
      error: (err) => {
        this.consultandoIa = false;
        const msg = err.status === 429
          ? 'Hiciste varias consultas seguidas. Esperá un minuto e intentá de nuevo.'
          : (err?.error?.message ?? 'No se pudo obtener el diagnóstico preliminar.');
        this.toastr.warning(msg, 'Diagnóstico preliminar');
      },
    });
  }

  /**
   * Punto 7: si el cliente no eligió el tipo y la IA lo detectó, se precarga en el combo.
   * emitEvent:false evita que el cambio borre el diagnóstico recién obtenido.
   */
  private aplicarTipoDetectado(d: DiagnosticoIa): void {
    const ctrl = this.equipo.get('sse_tdiid')!;
    const tipo = d.tipo_dispositivo;
    if (ctrl.value || !tipo?.detectado) return;
    if (this.config?.tipos_dispositivo.some((t) => t.tdi_id === tipo.tdi_id)) {
      ctrl.setValue(tipo.tdi_id, { emitEvent: false });
      this.toastr.info(`Identificamos tu equipo como: ${tipo.tdi_descri}. Podés cambiarlo si no es correcto.`, 'Tipo de equipo');
    }
  }

  // ---------- Paso 2 ----------

  elegirModalidad(m: ModalidadIngreso): void {
    this.ingreso.patchValue({ modalidad: m });
  }

  elegirSucursal(id: number): void {
    this.ingreso.patchValue({ sse_sucid: id });
  }

  elegirDireccion(id: number): void {
    this.ingreso.patchValue({ sse_dirid: id });
    this.calcularCosto(id);
  }

  private calcularCosto(dirId: number): void {
    this.calculandoCosto = true;
    this.service.costoRetiro(dirId).subscribe({
      next: (res) => { this.costoRetiro = res.costo; this.calculandoCosto = false; },
      error: () => { this.costoRetiro = null; this.calculandoCosto = false; },
    });
  }

  onDireccionGuardada(dir: Direccion): void {
    if (!this.config) return;
    this.config.direcciones = [dir, ...this.config.direcciones.filter((d) => d.dir_id !== dir.dir_id)];
    this.elegirDireccion(dir.dir_id);
  }

  // ---------- Navegación ----------

  siguiente(): void {
    const grupo = this.paso === 1 ? this.equipo : this.ingreso;
    if (grupo.invalid) {
      grupo.markAllAsTouched();
      this.toastr.warning('Completá los campos marcados.', 'Servicio técnico');
      return;
    }
    this.paso = (this.paso + 1) as Paso;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  anterior(): void {
    if (this.paso > 1) this.paso = (this.paso - 1) as Paso;
  }

  // ---------- Resumen ----------

  get tipoDispositivo(): string {
    return this.config?.tipos_dispositivo.find((t) => t.tdi_id === this.equipo.getRawValue().sse_tdiid)?.tdi_descri
      ?? 'Lo identificará nuestro sistema';   // Punto 7
  }

  get lineaElegida(): string {
    const l = this.config?.lineas_garantia.find((x) => x.dpd_id === this.equipo.value.sse_dpdid);
    return l ? `${l.producto} (${l.ped_numero})` : '';
  }

  get lugarIngreso(): string {
    if (this.esDomicilio) {
      return this.config?.direcciones.find((d) => d.dir_id === this.ingreso.value.sse_dirid)?.resumen ?? '';
    }
    return this.config?.sucursales.find((s) => s.suc_id === this.ingreso.value.sse_sucid)?.suc_nombre ?? '';
  }

  // ---------- Envío ----------

  enviar(): void {
    if (this.confirmacion.invalid) {
      this.confirmacion.markAllAsTouched();
      return;
    }

    const e = this.equipo.getRawValue();   // incluye los campos bloqueados del reclamo
    const i = this.ingreso.value;
    const datos = new FormData();

    datos.append('sse_garant', e.sse_garant ? '1' : '0');
    if (this.reclamoDe) datos.append('sse_sseori', this.reclamoDe);
    else if (e.sse_garant && e.sse_dpdid) datos.append('sse_dpdid', String(e.sse_dpdid));
    if (e.sse_tdiid) datos.append('sse_tdiid', String(e.sse_tdiid));   // Punto 7: opcional
    datos.append('sse_marca', e.sse_marca.trim());
    datos.append('sse_modelo', e.sse_modelo.trim());
    if (e.sse_nroser?.trim()) datos.append('sse_nroser', e.sse_nroser.trim());
    datos.append('sse_proble', e.sse_proble.trim());
    if (e.sse_acceso?.trim()) datos.append('sse_acceso', e.sse_acceso.trim());

    datos.append('modalidad', i.modalidad);
    if (i.modalidad === 'sucursal') datos.append('sse_sucid', String(i.sse_sucid));
    else datos.append('sse_dirid', String(i.sse_dirid));
    datos.append('sse_fecing', i.sse_fecing);

    datos.append('acepta_condiciones', '1');
    if (this.diagnosticoIa?.disponible) datos.append('dpr_id', String(this.diagnosticoIa.dpr_id));
    this.fotos.forEach((f) => datos.append('fotos[]', f.archivo));

    this.enviando = true;
    this.service.crear(datos).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Servicio técnico');
        this.router.navigate(['/mis-servicios', res.data.sse_numero], { replaceUrl: true });
      },
      error: (err) => {
        this.enviando = false;
        if (err.status === 422 && err.error?.errors) {
          this.mostrarErrores(err.error.errors);
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar la solicitud', 'Servicio técnico');
      },
    });
  }

  /** Coloca cada error junto a su campo y vuelve al paso donde está el primero. */
  private mostrarErrores(errores: Record<string, string[]>): void {
    let primerPaso: Paso | null = null;

    Object.entries(errores).forEach(([campo, msgs]) => {
      if (campo.startsWith('fotos')) {
        this.errorFotos = msgs[0];
        primerPaso ??= 1;
        return;
      }
      for (const [paso, grupo] of [[1, this.equipo], [2, this.ingreso], [3, this.confirmacion]] as [Paso, FormGroup][]) {
        const control = grupo.get(campo === 'modalidad' ? 'modalidad' : campo);
        if (control) {
          control.setErrors({ backend: msgs[0] });
          control.markAsTouched();
          primerPaso = primerPaso ? (Math.min(primerPaso, paso) as Paso) : paso;
          return;
        }
      }
      this.toastr.error(msgs[0], 'Servicio técnico');
    });

    if (primerPaso) this.paso = primerPaso;
    this.toastr.error('Revisá los datos marcados.', 'Servicio técnico');
  }
}