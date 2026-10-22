import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, DestroyRef, OnDestroy, OnInit, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Direccion } from '../../../core/models/direccion.model';
import { ConfigServicio, ModalidadIngreso } from '../../../core/models/servicio-tecnico.model';
import { ServicioTecnicoService } from '../../../core/services/servicio-tecnico.service';
import { FormDireccionComponent } from '../../checkout/form-direccion/form-direccion.component';

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
  imports: [CommonModule, CurrencyPipe, ReactiveFormsModule, RouterModule, FormDireccionComponent],
  templateUrl: './solicitud-servicio.component.html',
  styleUrl: './solicitud-servicio.component.css',
})
export class SolicitudServicioComponent implements OnInit, OnDestroy {
  private readonly destroyRef = inject(DestroyRef);

  readonly MAX_MB = 5;
  readonly hoy = new Date().toISOString().substring(0, 10);

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
        sse_tdiid: [null as number | null, Validators.required],
        sse_marca: ['', [Validators.required, Validators.maxLength(50)]],
        sse_modelo: ['', [Validators.required, Validators.maxLength(80)]],
        sse_nroser: ['', Validators.maxLength(80)],
        sse_proble: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(500)]],
        sse_acceso: ['', Validators.maxLength(255)],
      }),
      ingreso: this.fb.group({
        modalidad: ['sucursal' as ModalidadIngreso, Validators.required],
        sse_sucid: [null as number | null, Validators.required],
        sse_dirid: [null as number | null],
        sse_fecing: [this.hoy, Validators.required],
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

  onFotos(event: Event): void {
    const input = event.target as HTMLInputElement;
    const max = this.config?.max_fotos ?? 5;
    this.errorFotos = null;

    for (const archivo of Array.from(input.files ?? [])) {
      if (this.fotos.length >= max) { this.errorFotos = `Podés adjuntar hasta ${max} fotos.`; break; }
      if (!/^image\/(jpeg|png|webp)$/.test(archivo.type)) { this.errorFotos = `"${archivo.name}" no es JPG, PNG o WEBP.`; continue; }
      if (archivo.size > this.MAX_MB * 1024 * 1024) { this.errorFotos = `"${archivo.name}" supera los ${this.MAX_MB} MB.`; continue; }
      this.fotos.push({ archivo, preview: URL.createObjectURL(archivo) });
    }
    input.value = '';
  }

  quitarFoto(i: number): void {
    URL.revokeObjectURL(this.fotos[i].preview);
    this.fotos.splice(i, 1);
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
    return this.config?.tipos_dispositivo.find((t) => t.tdi_id === this.equipo.getRawValue().sse_tdiid)?.tdi_descri ?? '';
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
    datos.append('sse_tdiid', String(e.sse_tdiid));
    datos.append('sse_marca', e.sse_marca);
    datos.append('sse_modelo', e.sse_modelo);
    if (e.sse_nroser) datos.append('sse_nroser', e.sse_nroser);
    datos.append('sse_proble', e.sse_proble);
    if (e.sse_acceso) datos.append('sse_acceso', e.sse_acceso);

    datos.append('modalidad', i.modalidad);
    if (i.modalidad === 'sucursal') datos.append('sse_sucid', String(i.sse_sucid));
    else datos.append('sse_dirid', String(i.sse_dirid));
    datos.append('sse_fecing', i.sse_fecing);

    datos.append('acepta_condiciones', '1');
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
