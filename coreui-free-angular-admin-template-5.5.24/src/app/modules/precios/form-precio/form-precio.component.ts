import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Precio, ActualizarPrecioPayload, OrigenMargen } from '../models/precio.model';
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
    // max 1000: un markup de "100000%" es un error de tipeo, no un
    // negocio real (mismo criterio que ya tenías).
    this.form = this.fb.group({
      origenMinorista: ['general' as OrigenMargen],
      valorMinorista: [null as number | null, [Validators.min(0), Validators.max(1000)]],
      origenMayorista: ['general' as OrigenMargen],
      valorMayorista: [null as number | null, [Validators.min(0), Validators.max(1000)]],
      origenUmbral: ['general' as OrigenMargen],
      valorUmbral: [null as number | null, [Validators.min(1)]],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.precio) {
      this.form.reset({
        origenMinorista: this.precio.minorista.origen,
        valorMinorista: this.precio.minorista.origen === 'individual' ? this.precio.minorista.margen : null,
        origenMayorista: this.precio.mayorista.origen,
        valorMayorista: this.precio.mayorista.origen === 'individual' ? this.precio.mayorista.margen : null,
        origenUmbral: this.precio.umbral_mayorista.origen,
        valorUmbral:
          this.precio.umbral_mayorista.origen === 'individual' ? this.precio.umbral_mayorista.cantidad : null,
      });
      this.guardando = false;
    }
  }

  private round2(n: number): number {
    return Math.round(n * 100) / 100;
  }

  // Redondea a 2 decimales apenas el usuario termina de escribir (blur),
  // igual que redondearMargen() original pero reutilizable para los 3 campos.
  redondear(campo: 'valorMinorista' | 'valorMayorista' | 'valorUmbral'): void {
    const valor = this.form.value[campo];
    if (valor === null || valor === '') return;
    this.form.patchValue({ [campo]: this.round2(Number(valor)) });
  }

  // Previsualizacion en vivo (igual que el "calculo" original), ahora
  // separada por tier: usa el override si está activo, o si no, el
  // margen efectivo que ya trae el producto desde el backend.
  private calcular(margenEfectivo: number | null): { ganancia: number; sinIva: number; iva: number; final: number } | null {
    const costo = this.precio?.costo_promedio;
    const tasa = this.precio?.tiv_tasa ?? 0;

    if (costo === null || costo === undefined || margenEfectivo === null) {
      return null;
    }

    const ganancia = this.round2(costo * (margenEfectivo / 100));
    const sinIva = this.round2(costo + ganancia);
    const iva = this.round2(sinIva * (tasa / 100));
    const final = this.round2(sinIva + iva);

    return { ganancia, sinIva, iva, final };
  }

  get calculoMinorista() {
    const v = this.form.value;
    const margen = v.origenMinorista === 'individual' ? v.valorMinorista : this.precio?.minorista.margen ?? null;
    return this.calcular(margen);
  }

  get calculoMayorista() {
    const v = this.form.value;
    const margen = v.origenMayorista === 'individual' ? v.valorMayorista : this.precio?.mayorista.margen ?? null;
    return this.calcular(margen);
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

    const v = this.form.value;

    const payload: ActualizarPrecioPayload = {
      prd_marmin: v.origenMinorista === 'individual' ? v.valorMinorista : null,
      prd_marmaj: v.origenMayorista === 'individual' ? v.valorMayorista : null,
      prd_canminmaj: v.origenUmbral === 'individual' ? v.valorUmbral : null,
    };

    this.guardando = true;

    this.service.update(this.precio.prd_id, payload).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Precio actualizado', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          const mapa: Record<string, string> = {
            prd_marmin: 'valorMinorista',
            prd_marmaj: 'valorMayorista',
            prd_canminmaj: 'valorUmbral',
          };
          Object.keys(err.error.errors).forEach((campoBackend) => {
            const campoForm = mapa[campoBackend] ?? campoBackend;
            this.form.get(campoForm)?.setErrors({ backend: err.error.errors[campoBackend][0] });
            this.form.get(campoForm)?.markAsTouched();
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