import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { TipoComprobante } from '../models/tipo-comprobante.model';
import { TiposComprobanteService } from '../service/tipos-comprobante.service';

@Component({
  selector: 'app-form-tipo-comprobante',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-tipo-comprobante.component.html',
})
export class FormTipoComprobanteComponent implements OnChanges {
  @Input() visible = false;
  @Input() tipoComprobante: TipoComprobante | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private tiposComprobanteService: TiposComprobanteService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      tco_codigo:  ['', [Validators.required, Validators.pattern(/^[0-9]{3}$/)]],
      tco_descri:  ['', [Validators.required, Validators.maxLength(45)]],
      tco_reqtim:  [1],
      tco_afecstk: [0],
      tco_estado:  [1],
    });
  }

  get esEdicion(): boolean {
    return !!this.tipoComprobante?.tco_id;
  }

  // El efecto sobre el stock define el signo de los movimientos generados:
  // cambiarlo con documentos ya emitidos descuadraria el inventario.
  get bloquearEfecto(): boolean {
    return this.esEdicion && (this.tipoComprobante?.usos_count ?? 0) > 0;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        tco_codigo:  this.tipoComprobante?.tco_codigo ?? '',
        tco_descri:  this.tipoComprobante?.tco_descri ?? '',
        tco_reqtim:  this.tipoComprobante?.tco_reqtim ?? 1,
        tco_afecstk: this.tipoComprobante?.tco_afecstk ?? 0,
        tco_estado:  this.tipoComprobante?.tco_estado ?? 1,
      });

      this.bloquearEfecto
        ? this.form.get('tco_afecstk')?.disable()
        : this.form.get('tco_afecstk')?.enable();

      this.guardando = false;
    }
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
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

    const datos: Partial<TipoComprobante> = { ...this.form.getRawValue() };

    const peticion = this.esEdicion
      ? this.tiposComprobanteService.update(this.tipoComprobante!.tco_id!, datos)
      : this.tiposComprobanteService.create(datos);

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

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el tipo de comprobante', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
