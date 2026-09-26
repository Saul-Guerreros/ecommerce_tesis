import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ButtonModule, FormModule, GridModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Marca, Producto, TipoIva, UnidadMedida } from '../../../core/models/producto.model';
import { ProductoService } from '../../../core/services/producto.service';
import { debounceTime, distinctUntilChanged, switchMap } from 'rxjs/operators';
import { Subject } from 'rxjs';

/**
 * Formulario de PRODUCTO: solo datos de inventario/compra (código, marca,
 * descripción interna, precio, stock, estado). Categoría, título web,
 * descripción para la tienda e imágenes se manejan en el apartado
 * "Publicación Web" (ver publicacion-web.component.ts), disponible una
 * vez que el producto ya fue creado.
 */
@Component({
  selector: 'app-producto-form',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    ButtonModule,
    FormModule,
    GridModule,
    ModalModule,
    SpinnerModule,
  ],
  templateUrl: './producto-form.component.html',
})
export class ProductoFormComponent implements OnChanges {
  busquedaPadre$ = new Subject<string>();
resultadosPadre: Producto[] = [];
productoPadreSeleccionado: Producto | null = null;
  @Input() visible = true;
  @Input() producto: Producto | null = null;
  @Input() marcas: Marca[] = [];
  @Input() tiposIva: TipoIva[] = [];
  @Input() unidadesMedida: UnidadMedida[] = [];

  @Output() cerrar = new EventEmitter<void>();
  @Output() guardado = new EventEmitter<void>();

  guardando = false;
  error: string | null = null;

  // Se declara con "!" y se construye dentro del constructor (en vez de
  // como inicializador de campo) para poder usar "this.fb" -inyectado por
  // parameter property- al armarlo.
  form!: FormGroup;

  constructor(private fb: FormBuilder, private productoService: ProductoService) {
    this.form = this.fb.group({
      prd_padre_id: [null as number | null],
      prd_codinv: ['', [Validators.required, Validators.maxLength(45)]],
      prd_descri: ['', [Validators.required]],
      // null en vez de 0: así el input numérico nace vacío y no hay que borrar
      // un "0" cada vez que el usuario quiere escribir su propio monto/cantidad.
      prd_prevta: [null as number | null, [Validators.required, Validators.min(0)]],
      prd_margen: [null as number | null, [Validators.min(0)]],
      prd_stkmin: [null as number | null, [Validators.required, Validators.min(0)]],
      prd_marid: [null as number | null, [Validators.required]],
      prd_tivid: [null as number | null, [Validators.required]],
      prd_umdid: [null as number | null, [Validators.required]],
      prd_estado: [1, [Validators.required]],
      // true por defecto: la mayoría de los productos sí afecta stock;
      // se desmarca para casos como un servicio técnico.
      prd_afestk: [true],
    });

    this.busquedaPadre$.pipe(
  debounceTime(300),
  distinctUntilChanged(),
  switchMap((texto) => this.productoService.listar({ search: texto, per_page: 20 }))
).subscribe((resp) => {
  this.resultadosPadre = resp.data.filter(
    (p) => !p.prd_padre_id && p.prd_id !== this.producto?.prd_id
  );
});

  }

  buscarPadre(texto: string): void {
  if (texto.trim().length < 2) { this.resultadosPadre = []; return; }
  this.busquedaPadre$.next(texto);
  }

  elegirPadre(p: Producto): void {
  this.productoPadreSeleccionado = p;
  this.form.patchValue({ prd_padre_id: p.prd_id! });
  this.resultadosPadre = [];
  }

  quitarPadre(): void {
  this.productoPadreSeleccionado = null;
  this.form.patchValue({ prd_padre_id: null });
  }

  get esEdicion(): boolean {
    return !!this.producto?.prd_id;
  }

  ngOnChanges(): void {
    this.error = null;
	const duplicando = !!this.producto && !this.producto.prd_id;

    if (this.producto) {
      this.form.patchValue({
        prd_codinv: duplicando ? '' : this.producto.prd_codinv, // el código SIEMPRE es único, nunca se copia
        prd_descri: this.producto.prd_descri,
        prd_prevta: this.producto.prd_prevta,
        prd_margen: this.producto.prd_margen ?? null,
        prd_stkmin: this.producto.prd_stkmin,
        prd_marid: this.producto.prd_marid,
        prd_tivid: this.producto.prd_tivid,
        prd_umdid: this.producto.prd_umdid,
        prd_estado: this.producto.prd_estado,
        prd_afestk: this.producto.prd_afestk ?? true,
       prd_padre_id: this.producto.prd_padre_id ?? null,
      });
	  
	   this.productoPadreSeleccionado = duplicando
      ? ({ prd_id: this.producto.prd_padre_id ?? this.producto.prd_id, prd_codinv: this.producto.prd_codinv } as Producto)
      : (this.producto.padre ?? null);
    } else {
      this.form.reset({
        prd_codinv: '',
        prd_descri: '',
        prd_prevta: null,
        prd_margen: null,
        prd_stkmin: null,
        prd_marid: null,
        prd_tivid: null,
        prd_umdid: null,
        prd_estado: 1,
        prd_afestk: true,
        prd_padre_id: null
      });
	  this.productoPadreSeleccionado = null;
    }
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.error = null;

    const datos = this.form.value as Partial<Producto>;

    const request$ = this.esEdicion
      ? this.productoService.actualizar(this.producto!.prd_id!, datos)
      : this.productoService.crear(datos);

    request$.subscribe({
      next: () => {
        this.guardando = false;
        this.guardado.emit();
      },
      error: (err) => {
        this.guardando = false;
        this.error =
          err?.error?.message ?? 'No se pudo guardar el producto. Revisa los datos ingresados.';
      },
    });
  }

  cerrarModal(): void {
    this.visible = false;
    this.cerrar.emit();
  }

  onVisibleChange(valor: boolean): void {
    this.visible = valor;
    if (!valor) {
      this.cerrar.emit();
    }
  }
}