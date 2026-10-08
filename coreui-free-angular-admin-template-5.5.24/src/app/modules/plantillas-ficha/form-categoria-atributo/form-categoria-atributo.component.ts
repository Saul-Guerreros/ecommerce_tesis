import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  AbstractControl, FormBuilder, FormGroup, ReactiveFormsModule, ValidationErrors, Validators,
} from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import {
  AtributoOption, CategoriaAtributo, SubcategoriaPlantilla,
} from '../models/categoria-atributo.model';
import { PlantillasFichaService } from '../service/plantillas-ficha.service';

@Component({
  selector: 'app-form-categoria-atributo',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-categoria-atributo.component.html',
})
export class FormCategoriaAtributoComponent implements OnChanges {
  @Input() visible = false;
  @Input() item: CategoriaAtributo | null = null;
  @Input() categoria: SubcategoriaPlantilla | null = null;
  @Input() atributos: AtributoOption[] = [];
  @Input() atributosUsados: number[] = [];

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  private readonly tipos: Record<number, string> = {
    1: 'Texto', 2: 'Número', 3: 'Seleccionable', 4: 'Selección múltiple',
  };

  constructor(
    private fb: FormBuilder,
    private plantillasService: PlantillasFichaService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      cta_atbid:  [null, [Validators.required, (c: AbstractControl) => this.atributoRepetido(c)]],
      cta_orden:  [null, [Validators.min(1), Validators.max(99)]],
      cta_obliga: [0, [Validators.required]],
    });
  }

  get esEdicion(): boolean {
    return !!this.item?.cta_id;
  }

  /** Oculta los atributos que ya están en la plantilla (menos el que se edita). */
  get atributosDisponibles(): AtributoOption[] {
    return this.atributos.filter(
      (a) => !this.atributosUsados.includes(a.atb_id) || a.atb_id === this.item?.cta_atbid
    );
  }

  descripcionTipo(tipo: number): string {
    return this.tipos[tipo] ?? '';
  }

  ngOnChanges(changes: SimpleChanges): void {
    // Reset al abrir: "Nuevo" siempre arranca limpio y "Editar" carga el registro.
    if (changes['visible'] && this.visible) {
      this.form.reset({
        cta_atbid:  this.item?.cta_atbid ?? null,
        cta_orden:  this.item?.cta_orden ?? null,
        cta_obliga: this.item?.cta_obliga ?? 0,
      });
      this.guardando = false;
    }
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorCampo(campo: string): string {
    const errores = this.form.get(campo)?.errors;
    if (!errores) return '';
    if (errores['backend']) return errores['backend'];
    if (errores['repetido']) return 'Este atributo ya está en la plantilla de esta subcategoría.';
    if (errores['required']) return 'Este campo es obligatorio.';
    if (errores['min'] || errores['max']) return 'El orden debe estar entre 1 y 99.';
    return 'Valor no válido.';
  }

  guardar(): void {
    if (this.form.invalid || !this.categoria) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    const datos: Partial<CategoriaAtributo> = {
      ...this.form.value,
      cta_orden: this.form.value.cta_orden || null, // vacío = el backend lo pone al final
    };

    const peticion = this.esEdicion
      ? this.plantillasService.update(this.item!.cta_id!, datos)
      : this.plantillasService.create({ ...datos, cta_catid: this.categoria.cat_id });

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Plantilla guardada', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => this.handleError(err),
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  private atributoRepetido(control: AbstractControl): ValidationErrors | null {
    const id = control.value;
    if (!id || id === this.item?.cta_atbid) return null;
    return this.atributosUsados?.includes(id) ? { repetido: true } : null;
  }

  private handleError(err: any): void {
    this.guardando = false;

    if (err.status === 422 && err.error?.errors) {
      Object.keys(err.error.errors).forEach((campo) => {
        // cta_catid no tiene control propio: su error se muestra en el atributo.
        const control = this.form.get(campo) ?? this.form.get('cta_atbid');
        control?.setErrors({ backend: err.error.errors[campo][0] });
        control?.markAsTouched();
      });
      this.toastr.warning('Revise los campos marcados', 'Validación');
      return;
    }

    this.toastr.error(err?.error?.message ?? 'No se pudo guardar la plantilla', 'Error');
  }
}
