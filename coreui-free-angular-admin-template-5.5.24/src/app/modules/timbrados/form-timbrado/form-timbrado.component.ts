import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Timbrado, TipoComprobanteOption, SucursalOption } from '../models/timbrado.model';
import { TimbradosService } from '../service/timbrados.service';

@Component({
  selector: 'app-form-timbrado',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-timbrado.component.html',
})
export class FormTimbradoComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() timbrado: Timbrado | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  tipos: TipoComprobanteOption[] = [];
  sucursales: SucursalOption[] = [];

  constructor(
    private fb: FormBuilder,
    private timbradosService: TimbradosService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      tim_tcoid:  [null, [Validators.required]],
      tim_sucid:  [null, [Validators.required]],
      tim_numero: ['', [Validators.required, Validators.minLength(8), Validators.maxLength(8)]],
      tim_puntex: ['', [Validators.required, Validators.pattern(/^[0-9]{3}$/)]],
      tim_fechin: ['', [Validators.required]],
      tim_fechfn: ['', [Validators.required]],
      tim_nroini: [null, [Validators.required, Validators.min(1)]],
      tim_nrofin: [null, [Validators.required]],
      tim_observ: ['', [Validators.maxLength(100)]],
      tim_estado: [1],
    });
  }

  ngOnInit(): void {
    this.timbradosService.getConfig().subscribe({
      next: (res) => {
        this.tipos      = res.tipos;
        this.sucursales = res.sucursales;
      },
      error: () => this.toastr.error('No se pudieron cargar tipos de comprobante y sucursales', 'Error'),
    });
  }

  get esEdicion(): boolean {
    return !!this.timbrado?.tim_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        tim_tcoid:  this.timbrado?.tim_tcoid ?? null,
        tim_sucid:  this.timbrado?.tim_sucid ?? null,
        tim_numero: this.timbrado?.tim_numero ?? '',
        tim_puntex: this.timbrado?.tim_puntex ?? '',
        tim_fechin: this.timbrado?.tim_fechin ?? '',
        tim_fechfn: this.timbrado?.tim_fechfn ?? '',
        tim_nroini: this.timbrado?.tim_nroini ?? null,
        tim_nrofin: this.timbrado?.tim_nrofin ?? null,
        tim_observ: this.timbrado?.tim_observ ?? '',
        tim_estado: this.timbrado?.tim_estado ?? 1,
      });
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

    // tim_nroact nunca se envia: el formulario no lo conoce como campo
    // editable, lo gestiona el backend.
    const datos: Partial<Timbrado> = {
      ...this.form.value,
      tim_observ: this.form.value.tim_observ || null,
    };

    const peticion = this.esEdicion
      ? this.timbradosService.update(this.timbrado!.tim_id!, datos)
      : this.timbradosService.create(datos);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Operación realizada', 'Éxito');
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

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el timbrado', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}