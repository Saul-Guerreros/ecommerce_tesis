import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Producto, MarcaOption, TipoIvaOption, UnidadMedidaOption } from '../models/producto.model';
import { ProductosService } from '../service/productos.service';

@Component({
  selector: 'app-form-producto',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-producto.component.html',
})
export class FormProductoComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() producto: Producto | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  marcas: MarcaOption[] = [];
  tiposIva: TipoIvaOption[] = [];
  unidades: UnidadMedidaOption[] = [];

  constructor(
    private fb: FormBuilder,
    private service: ProductosService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      prd_marid:  [null, [Validators.required]],
      prd_tivid:  [null, [Validators.required]],
      prd_umdid:  [null, [Validators.required]],
      prd_codinv: ['', [Validators.required, Validators.maxLength(45)]],
      prd_descri: ['', [Validators.required, Validators.maxLength(150)]],
      prd_prevta: [null, [Validators.required, Validators.min(0)]],
      prd_margen: [null, [Validators.min(0)]],
      prd_stkmin: [null, [Validators.required, Validators.min(0)]],
      prd_afestk: [1],
      prd_estado: [1],
    });
  }

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (res) => {
        this.marcas   = res.marcas;
        this.tiposIva = res.tipos_iva;
        this.unidades = res.unidades;
      },
      error: () => this.toastr.error('No se pudieron cargar marcas, IVA y unidades', 'Error'),
    });
  }

  get esEdicion(): boolean {
    return !!this.producto?.prd_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        prd_marid:  this.producto?.prd_marid ?? null,
        prd_tivid:  this.producto?.prd_tivid ?? null,
        prd_umdid:  this.producto?.prd_umdid ?? null,
        prd_codinv: this.producto?.prd_codinv ?? '',
        prd_descri: this.producto?.prd_descri ?? '',
        prd_prevta: this.producto?.prd_prevta ?? null,
        prd_margen: this.producto?.prd_margen ?? null,
        prd_stkmin: this.producto?.prd_stkmin ?? null,
        prd_afestk: this.producto?.prd_afestk ?? 1,
        prd_estado: this.producto?.prd_estado ?? 1,
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

    // form.value nunca incluye stock_actual: no es un FormControl, no se
    // puede editar desde este formulario aunque se quisiera.
    const peticion = this.esEdicion
      ? this.service.update(this.producto!.prd_id!, this.form.value)
      : this.service.create(this.form.value);

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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el producto', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}