import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Calle, CiudadOption, BarrioOption } from '../models/calle.model';
import { CallesService } from '../service/calles.service';

/**
 * Modal unico para alta y edicion.
 * Si recibe [calle] con cal_id -> modo edicion (PUT).
 * Si recibe null -> modo alta (POST).
 *
 * calle ya no tiene columna cal_ciuid: la ciudad es solo un FILTRO visual
 * para acotar el combo de barrio. 'ciudadFiltro' vive fuera del FormGroup
 * a proposito y nunca se envia al backend; lo unico que se guarda es
 * cal_barid.
 */
@Component({
  selector: 'app-form-calle',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, FormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-calle.component.html',
})
export class FormCalleComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() calle: Calle | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  ciudades: CiudadOption[] = [];
  private todosLosBarrios: BarrioOption[] = [];
  barriosFiltrados: BarrioOption[] = [];

  // Filtro visual, no forma parte del payload que se envia al backend.
  ciudadFiltro: number | null = null;

  constructor(
    private fb: FormBuilder,
    private callesService: CallesService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      cal_barid:  [null, [Validators.required]],
      cal_descri: ['', [Validators.required, Validators.maxLength(80)]],
      cal_estado: [1],
    });
  }

  ngOnInit(): void {
    this.callesService.getConfig().subscribe({
      next: (res) => {
        this.ciudades        = res.ciudades;
        this.todosLosBarrios = res.barrios;
        this.sincronizarFiltroConRegistro();
      },
      error: () => this.toastr.error('No se pudieron cargar ciudades y barrios', 'Error'),
    });
  }

  get esEdicion(): boolean {
    return !!this.calle?.cal_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        cal_barid:  this.calle?.cal_barid ?? null,
        cal_descri: this.calle?.cal_descri ?? '',
        cal_estado: this.calle?.cal_estado ?? 1,
      });
      this.sincronizarFiltroConRegistro();
      this.guardando = false;
    }
  }

  /**
   * En modo edicion, cal_barid ya trae un valor pero no sabemos a que
   * ciudad pertenece (no llega cal_ciuid). Se busca el barrio dentro de
   * la config ya cargada y se toma su bar_ciuid para preseleccionar el
   * filtro de ciudad y mostrar el combo de barrio ya acotado.
   */
  private sincronizarFiltroConRegistro(): void {
    if (this.calle?.cal_barid && this.todosLosBarrios.length) {
      const barrio = this.todosLosBarrios.find((b) => b.bar_id === this.calle!.cal_barid);
      this.ciudadFiltro = barrio?.bar_ciuid ?? null;
    } else {
      this.ciudadFiltro = null;
    }

    this.filtrarBarrios(this.ciudadFiltro);
  }

  /**
   * Al cambiar el filtro de ciudad se limpia cal_barid: el barrio anterior
   * pertenece a otra ciudad y ya no aparece en el combo filtrado.
   */
  onCiudadFiltroChange(): void {
    this.form.patchValue({ cal_barid: null });
    this.filtrarBarrios(this.ciudadFiltro);
  }

  private filtrarBarrios(ciudadId: number | null): void {
    this.barriosFiltrados = ciudadId
      ? this.todosLosBarrios.filter((b) => b.bar_ciuid === Number(ciudadId))
      : [];
  }

  campoInvalido(campo: string): boolean {
    const control = this.form.get(campo);
    return !!control && control.invalid && (control.touched || control.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    // form.value solo trae cal_barid, cal_descri, cal_estado: ciudadFiltro
    // nunca se incluye porque no es un FormControl del grupo.
    const peticion = this.esEdicion
      ? this.callesService.update(this.calle!.cal_id!, this.form.value)
      : this.callesService.create(this.form.value);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Operacion realizada', 'Exito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;

        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            this.form.get(campo)?.setErrors({ backend: err.error.errors[campo][0] });
            this.form.get(campo)?.markAsTouched();
          });
          this.toastr.error('Revise los datos ingresados', 'Validacion');
          return;
        }

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la calle', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
