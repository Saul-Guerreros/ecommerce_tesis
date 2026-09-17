import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Producto, MarcaOption, TipoIvaOption, UnidadMedidaOption } from '../models/producto.model';
import { ProductosService } from '../service/productos.service';

import { EquivalenciaProductoComponent } from '../equivalencia-producto/equivalencia-producto.component';

@Component({
  selector: 'app-form-producto',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule, EquivalenciaProductoComponent],  
  templateUrl: './form-producto.component.html',
})
export class FormProductoComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() producto: Producto | null = null;
  @Input() soloLectura = false;

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
    // El costo, margen y precio de venta ya NO se muestran en este
    // formulario: viven enteramente en Gestión de Precios (minorista y
    // mayorista, con override por producto). Este form solo maneja los
    // datos propios del producto.
    this.form = this.fb.group({
      prd_marid:  [null, [Validators.required]],
      prd_tivid:  [null, [Validators.required]],
      prd_umdid:  [null, [Validators.required]],
      prd_codinv: ['', [Validators.required, Validators.maxLength(45)]],
      prd_descri: ['', [Validators.required, Validators.maxLength(150)]],
      prd_stkmin: [null, [Validators.required, Validators.min(0)]],
      prd_afestk: [1],
      prd_estado: [1],

      // Equivalencia inicial: solo se usa en modo creación (ver template).
      // En edición viaja siempre en false/null y el backend los ignora.
      tiene_equiv_compra: [false],
      peq_umdid:  [null],
      peq_factor: [null],
    });

    // Alterna los validadores de peq_umdid/peq_factor segun tiene_equiv_compra.
    this.form.get('tiene_equiv_compra')!.valueChanges.subscribe((tiene) => {
      this.actualizarValidadoresEquivalencia(!!tiene);
    });
  }

  private actualizarValidadoresEquivalencia(tiene: boolean): void {
    const umd = this.form.get('peq_umdid')!;
    const factor = this.form.get('peq_factor')!;

    if (tiene) {
      umd.setValidators([Validators.required]);
      factor.setValidators([Validators.required, Validators.min(0.0001)]);
    } else {
      umd.clearValidators();
      factor.clearValidators();
      umd.setValue(null);
      factor.setValue(null);
    }
    umd.updateValueAndValidity({ emitEvent: false });
    factor.updateValueAndValidity({ emitEvent: false });
  }

  // Unidades ofrecibles como alternativa: todo el catálogo MENOS la unidad
  // de stock ya elegida en prd_umdid (misma regla que valida el backend).
  get unidadesAlternativasDisponibles(): UnidadMedidaOption[] {
    const base = this.form.value.prd_umdid;
    return this.unidades.filter((u) => u.umd_id !== base);
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
        prd_stkmin: this.producto?.prd_stkmin ?? null,
        prd_afestk: this.producto?.prd_afestk ?? 1,
        prd_estado: this.producto?.prd_estado ?? 1,

        // Solo aplica al crear: siempre arranca en "No" (requisito 2),
        // y en edición no se usa (el CRUD de equivalencias ya lo maneja).
        tiene_equiv_compra: false,
        peq_umdid:  null,
        peq_factor: null,
      });
      this.actualizarValidadoresEquivalencia(false);
      this.guardando = false;

      if (this.soloLectura) {
        this.form.disable();
      } else {
        this.form.enable();
      }
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