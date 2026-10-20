import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { RelacionReferencia, ResumenCredito, SolicitudReferencia } from '../../../core/models/credito.model';
import { CreditoService } from '../../../core/services/credito.service';

/**
 * Paso 3 del wizard: Referencias Personales, con sus 2 sub-pasos
 * internos (Referencia 1/2, Referencia 2/2). A diferencia de Personal y
 * Laboral, acá cada sub-paso es un FormGroup DENTRO de un FormArray de
 * 2 posiciones fijas -- el instructivo pide exactamente 2 referencias,
 * no una lista abierta con [+ Agregar]. El backend sí acepta hasta 5
 * (ver SolicitudReferenciasRequest), así que el día que el negocio pida
 * una tercera referencia esto se extiende agregando un sub-paso acá sin
 * tocar la base ni el backend.
 *
 * La "Relación" se elige con tarjetas .ck-opcion--chica (mismo
 * tratamiento que "Tipo de vivienda" en el Paso 1 y "Yo mismo / Otra
 * persona" del checkout) en vez de un <select>: son 3 opciones fijas,
 * y visualmente es más rápido de completar que abrir un desplegable.
 */
@Component({
  selector: 'app-paso-referencias',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterModule],
  templateUrl: './paso-referencias.component.html',
  styleUrl: './paso-referencias.component.css',
})
export class PasoReferenciasComponent implements OnInit {
  form!: FormGroup;

  /** 1 = Referencia 1, 2 = Referencia 2. */
  subPaso = 1;

  modoEdicion = false;
  cargando = true;
  guardando = false;

  /** Totales del crédito, para el panel lateral. */
  resumen: ResumenCredito | null = null;

  /** Celular del propio solicitante (Paso 1), para la validación cruzada. */
  private celularSolicitante: string | null = null;

  readonly relaciones: { valor: RelacionReferencia; etiqueta: string }[] = [
    { valor: 'FAMILIAR', etiqueta: 'Familiar' },
    { valor: 'LABORAL', etiqueta: 'Laboral' },
    { valor: 'AMISTAD', etiqueta: 'Amistad' },
  ];

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
      referencias: this.fb.array([this.nuevaReferencia(), this.nuevaReferencia()]),
    });
  }

  private nuevaReferencia(): FormGroup {
    return this.fb.group({
      scf_nombre: ['', [Validators.required, Validators.maxLength(120)]],
      scf_celula: ['', [Validators.required, Validators.pattern(/^\+?[0-9\s\-]{6,20}$/)]],
      scf_relaci: ['', [Validators.required]],
    });
  }

  get referenciasArray(): FormArray {
    return this.form.get('referencias') as FormArray;
  }

  referenciaGrupo(indice: number): FormGroup {
    return this.referenciasArray.at(indice) as FormGroup;
  }

  /** Tarjeta de relación: evita repetir la lógica de patchValue en el HTML. */
  elegirRelacion(indice: number, valor: RelacionReferencia): void {
    this.referenciaGrupo(indice).patchValue({ scf_relaci: valor });
    this.referenciaGrupo(indice).get('scf_relaci')!.markAsTouched();
  }

  private cargar(): void {
    this.creditoService.config().subscribe({
      next: (resp) => {
        // El Paso 3 exige el Paso 2 ya guardado -- mismo candado que
        // SolicitudBorradorService::guardarReferencias() en el backend.
        if (!resp.laboral) {
          this.toastr.info('Completá primero los datos laborales.', 'Solicitud de Crédito');
          this.router.navigate(['/solicitar-credito/laboral']);
          return;
        }

        this.resumen = resp.resumen;
        this.celularSolicitante = this.normalizarCelular(resp.personal?.scr_celula ?? '');

        if (resp.referencias && resp.referencias.length >= 2) {
          this.precargarDesde(resp.referencias);
        }

        this.cargando = false;
      },
      error: () => {
        this.cargando = false;
        this.toastr.error('No pudimos cargar el formulario de crédito.', 'Error');
      },
    });
  }

  private precargarDesde(referencias: SolicitudReferencia[]): void {
    const ordenadas = [...referencias].sort((a, b) => a.scf_orden - b.scf_orden);

    ordenadas.slice(0, 2).forEach((ref, i) => {
      this.referenciaGrupo(i).patchValue({
        scf_nombre: ref.scf_nombre,
        scf_celula: ref.scf_celula,
        scf_relaci: ref.scf_relaci,
      });
    });
  }

  // ---------- Validación cruzada (espeja SolicitudReferenciasRequest::withValidator) ----------

  private normalizarCelular(valor: string): string {
    return (valor ?? '').replace(/\D/g, '');
  }

  /**
   * Corre recién al intentar avanzar/guardar, no en cada tecla: evita
   * marcar error mientras el cliente todavía está completando el otro
   * campo. Devuelve el primer problema encontrado, o null si está OK.
   */
  private validarCruzado(): string | null {
    const celulares = this.referenciasArray.value.map((r: any) => this.normalizarCelular(r.scf_celula));

    if (celulares[0] && celulares[0] === celulares[1]) {
      return 'Las dos referencias tienen el mismo número de celular. Cargá contactos distintos.';
    }

    if (this.celularSolicitante && celulares.includes(this.celularSolicitante)) {
      return 'No podés usar tu propio número como referencia.';
    }

    return null;
  }

  // ---------- Navegación entre sub-pasos (modo wizard) ----------

  private subPasoValido(subPaso: number): boolean {
    const grupo = this.referenciaGrupo(subPaso - 1);
    grupo.markAllAsTouched();
    return grupo.valid;
  }

  siguiente(): void {
    if (!this.subPasoValido(this.subPaso)) {
      return;
    }

    if (this.subPaso < 2) {
      this.subPaso++;
      return;
    }

    const error = this.validarCruzado();
    if (error) {
      this.toastr.warning(error, 'Revisá las referencias');
      return;
    }

    this.guardar();
  }

  atras(): void {
    if (this.subPaso > 1) {
      this.subPaso--;
      return;
    }
    this.router.navigate(['/solicitar-credito/laboral']);
  }

  // ---------- Modo edición (desde Confirmación) ----------

  cancelarEdicion(): void {
    this.router.navigate(['/solicitar-credito/confirmacion']);
  }

  actualizarDatos(): void {
    this.referenciasArray.markAllAsTouched();

    if (this.form.invalid) {
      this.toastr.warning('Revisá los campos marcados antes de actualizar.', 'Datos incompletos');
      return;
    }

    const error = this.validarCruzado();
    if (error) {
      this.toastr.warning(error, 'Revisá las referencias');
      return;
    }

    this.guardar();
  }

  // ---------- Guardado contra el backend ----------

  private guardar(): void {
    if (this.guardando) return;

    this.guardando = true;

    this.creditoService.guardarReferencias(this.referenciasArray.value).subscribe({
      next: () => {
        this.guardando = false;

        // A diferencia de Personal y Laboral, acá el wizard y el modo
        // edición terminan en el mismo lugar: el Paso 3 es el último
        // paso de datos y el instructivo manda directo a Confirmación
        // en los dos casos.
        this.router.navigate(['/solicitar-credito/confirmacion']);
      },
      error: (err) => {
        this.guardando = false;
        this.toastr.error(this.mensajeError(err), 'No se pudo guardar');
      },
    });
  }

  campoInvalido(indice: number, campo: string): boolean {
    const c = this.referenciaGrupo(indice).get(campo);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  private mensajeError(err: any): string {
    const errores = err?.error?.errors;
    if (errores) {
      const primeraClave = Object.keys(errores)[0];
      return errores[primeraClave]?.[0] ?? 'Intentá nuevamente.';
    }
    return err?.error?.message ?? 'Intentá nuevamente.';
  }
}