import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { GuardarLaboralRequest, ResumenCredito, SolicitudLaboral } from '../../../core/models/credito.model';
import { CreditoService } from '../../../core/services/credito.service';

/**
 * Paso 2 del wizard: Datos Laborales, con sus 2 sub-pasos internos
 * (Lugar de trabajo 1/2, Situación económica 2/2). Mismo esquema que
 * PasoPersonalComponent: un solo FormGroup, un índice local (subPaso)
 * que decide qué bloque se muestra, y un único POST al cerrar el
 * último sub-paso. Mismo estilo visual que el checkout (ck-layout de
 * dos columnas + resumen lateral sticky).
 */
@Component({
  selector: 'app-paso-laboral',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterModule],
  templateUrl: './paso-laboral.component.html',
  styleUrl: './paso-laboral.component.css',
})
export class PasoLaboralComponent implements OnInit {
  form!: FormGroup;

  /** 1 = Lugar de trabajo, 2 = Situación económica. */
  subPaso = 1;

  modoEdicion = false;
  cargando = true;
  guardando = false;

  /** Totales del crédito, para el panel lateral. */
  resumen: ResumenCredito | null = null;

  constructor(
    private fb: FormBuilder,
    private creditoService: CreditoService,
    private toastr: ToastrService,
    private router: Router,
    private route: ActivatedRoute,
  ) {}

  ngOnInit(): void {
    this.modoEdicion = this.route.snapshot.queryParamMap.get('editar') === '1';

    this.construirFormulario();
    this.cargar();
  }

  private construirFormulario(): void {
    this.form = this.fb.group({
      // 1/2 Lugar de trabajo
      scr_empres: ['', [Validators.required, Validators.maxLength(150)]],
      scr_dirlab: ['', [Validators.required, Validators.maxLength(200)]],
      scr_tellab: ['', [Validators.required, Validators.pattern(/^\+?[0-9\s\-]{6,20}$/)]],

      // 2/2 Situación económica
      scr_cargo: ['', [Validators.required, Validators.maxLength(100)]],
      scr_ingres: [null as number | null, [Validators.required, Validators.min(1)]],
      scr_fecing: ['', [Validators.required, this.validadorNoFutura()]],
    });
  }

  private cargar(): void {
    this.creditoService.config().subscribe({
      next: (resp) => {
        // El Paso 2 exige el Paso 1 ya guardado -- mismo candado que
        // aplica SolicitudBorradorService::guardarLaboral() en el
        // backend, acá solo evita el viaje de ida y vuelta inútil.
        if (!resp.personal) {
          this.toastr.info('Completá primero los datos personales.', 'Solicitud de Crédito');
          this.router.navigate(['/solicitar-credito/personal']);
          return;
        }

        this.resumen = resp.resumen;

        if (resp.laboral) {
          this.precargarDesde(resp.laboral);
        }

        this.cargando = false;
      },
      error: () => {
        this.cargando = false;
        this.toastr.error('No pudimos cargar el formulario de crédito.', 'Error');
      },
    });
  }

  private precargarDesde(l: SolicitudLaboral): void {
    this.form.patchValue({
      scr_empres: l.scr_empres,
      scr_dirlab: l.scr_dirlab,
      scr_tellab: l.scr_tellab,
      scr_cargo: l.scr_cargo,
      scr_ingres: l.scr_ingres,
      scr_fecing: l.scr_fecing,
    });
  }

  // ---------- Antigüedad calculada en vivo (solo presentación) ----------
  // Espeja SolicitudCredito::textoAntiguedad() del backend: se muestra
  // mientras el cliente escribe, pero NUNCA se envía -- el backend la
  // recalcula siempre desde scr_fecing, la antigüedad no se persiste
  // porque envejecería mal.

  get antiguedadTexto(): string | null {
    const fecha = this.form?.value?.scr_fecing;
    if (!fecha) return null;

    const desde = new Date(fecha);
    const hoy = new Date();

    if (desde > hoy) return null;

    let anios = hoy.getFullYear() - desde.getFullYear();
    let meses = hoy.getMonth() - desde.getMonth();

    if (hoy.getDate() < desde.getDate()) meses--;
    if (meses < 0) { anios--; meses += 12; }

    const partes: string[] = [];
    if (anios > 0) partes.push(`${anios} ${anios === 1 ? 'año' : 'años'}`);
    if (meses > 0) partes.push(`${meses} ${meses === 1 ? 'mes' : 'meses'}`);

    return partes.length ? partes.join(' ') : 'Menos de un mes';
  }

  private validadorNoFutura() {
    return (control: any) => {
      if (!control.value) return null;
      const fecha = new Date(control.value);
      const hoy = new Date();
      hoy.setHours(23, 59, 59, 999);
      return fecha <= hoy ? null : { fechaFutura: true };
    };
  }

  // ---------- Navegación entre sub-pasos (modo wizard) ----------

  private controlesDelSubPaso(subPaso: number): string[] {
    if (subPaso === 1) return ['scr_empres', 'scr_dirlab', 'scr_tellab'];
    return ['scr_cargo', 'scr_ingres', 'scr_fecing'];
  }

  private subPasoValido(subPaso: number): boolean {
    const controles = this.controlesDelSubPaso(subPaso);
    controles.forEach((c) => this.form.get(c)!.markAsTouched());
    return controles.every((c) => this.form.get(c)!.valid);
  }

  siguiente(): void {
    if (!this.subPasoValido(this.subPaso)) {
      return;
    }

    if (this.subPaso < 2) {
      this.subPaso++;
      return;
    }

    this.guardar();
  }

  atras(): void {
    if (this.subPaso > 1) {
      this.subPaso--;
      return;
    }
    this.router.navigate(['/solicitar-credito/personal']);
  }

  // ---------- Modo edición (desde Confirmación) ----------

  cancelarEdicion(): void {
    this.router.navigate(['/solicitar-credito/confirmacion']);
  }

  actualizarDatos(): void {
    this.form.markAllAsTouched();

    if (this.form.invalid) {
      this.toastr.warning('Revisá los campos marcados antes de actualizar.', 'Datos incompletos');
      return;
    }

    this.guardar(true);
  }

  // ---------- Guardado contra el backend ----------

  private guardar(volverAConfirmacion = false): void {
    if (this.guardando) return;

    this.guardando = true;
    const payload: GuardarLaboralRequest = this.form.value;

    this.creditoService.guardarLaboral(payload).subscribe({
      next: () => {
        this.guardando = false;

        if (volverAConfirmacion || this.modoEdicion) {
          this.router.navigate(['/solicitar-credito/confirmacion']);
        } else {
          this.router.navigate(['/solicitar-credito/referencias']);
        }
      },
      error: (err) => {
        this.guardando = false;
        this.toastr.error(this.mensajeError(err), 'No se pudo guardar');
      },
    });
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  private mensajeError(err: any): string {
    const errores = err?.error?.errors;
    if (errores) {
      const primerCampo = Object.keys(errores)[0];
      return errores[primerCampo]?.[0] ?? 'Intentá nuevamente.';
    }
    return err?.error?.message ?? 'Intentá nuevamente.';
  }
}