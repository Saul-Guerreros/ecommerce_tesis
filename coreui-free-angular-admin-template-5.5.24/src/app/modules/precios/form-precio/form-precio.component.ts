import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Precio } from '../models/precio.model';
import { PreciosService } from '../service/precios.service';

@Component({
  selector: 'app-form-precio',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-precio.component.html',
})
export class FormPrecioComponent implements OnChanges {
  @Input() visible = false;
  @Input() precio: Precio | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private service: PreciosService,
    private toastr: ToastrService
  ) {
    // max 1000: un markup de "100000%" es un error de tipeo, no un negocio
    // real. Ajustable si algun rubro lo necesita mas alto.
    this.form = this.fb.group({
      prd_margen: [null, [Validators.required, Validators.min(0), Validators.max(1000)]],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({ prd_margen: this.precio?.prd_margen ?? null });
      this.guardando = false;
    }
  }

  // Redondea a 2 decimales apenas el usuario termina de escribir (blur),
  // asi nunca se manda al backend algo como 30.45678.
  redondearMargen(): void {
    const valor = this.form.value.prd_margen;
    if (valor === null || valor === '') return;
    this.form.patchValue({ prd_margen: Math.round(Number(valor) * 100) / 100 });
  }

  private round2(n: number): number {
    return Math.round(n * 100) / 100;
  }

  // Previsualizacion en vivo de los 4 campos automaticos que pide el
  // ejemplo del txt. null si todavia no hay costo (producto nunca comprado).
  get calculo(): { ganancia: number; sinIva: number; iva: number; final: number } | null {
    const costo = this.precio?.costo_promedio;
    const margen = this.form.value.prd_margen;
    const tasa = this.precio?.tiv_tasa ?? 0;

    if (costo === null || costo === undefined || margen === null || margen === '') {
      return null;
    }

    const ganancia = this.round2(costo * (Number(margen) / 100));
    const sinIva   = this.round2(costo + ganancia);
    const iva      = this.round2(sinIva * (tasa / 100));
    const final    = this.round2(sinIva + iva);

    return { ganancia, sinIva, iva, final };
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.form.invalid || !this.precio) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    this.service.update(this.precio.prd_id, this.form.value.prd_margen).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Precio actualizado', 'Éxito');
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
          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo actualizar el precio', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}