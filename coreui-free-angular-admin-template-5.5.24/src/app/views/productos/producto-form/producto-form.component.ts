import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ButtonModule, FormModule, GridModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { environment } from '../../../../environments/environment';
import { Categoria, Marca, Producto } from '../../../core/models/producto.model';
import { ProductoService } from '../../../core/services/producto.service';

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
  @Input() visible = true;
  @Input() producto: Producto | null = null;
  @Input() categorias: Categoria[] = [];
  @Input() marcas: Marca[] = [];

  @Output() cerrar = new EventEmitter<void>();
  @Output() guardado = new EventEmitter<void>();

  guardando = false;
  error: string | null = null;

  // Imágenes ya subidas al servidor (solo en edición) se manejan vía `producto.imagenes`.
  // Estas dos listas son las imágenes NUEVAS que el usuario va seleccionando en este formulario,
  // se acumulan (no se reemplazan) cada vez que abre el selector de archivos.
  archivosSeleccionados: File[] = [];
  previews: string[] = [];

  // Se declara sin inicializar aquí y se construye dentro del constructor,
  // porque con "useDefineForClassFields" los inicializadores de campo corren
  // antes de que el constructor asigne "this.fb" (parameter property),
  // lo que provoca "Property 'fb' is used before its initialization".
  form: ReturnType<FormBuilder['group']>;

  constructor(private fb: FormBuilder, private productoService: ProductoService) {
    this.form = this.fb.group({
      prd_titulo: ['', [Validators.required, Validators.maxLength(45)]],
      prd_codinv: ['', [Validators.required, Validators.maxLength(45)]],
      prd_descri: ['', [Validators.required]],
      // null en vez de 0: así el input numérico nace vacío y no hay que borrar
      // un "0" cada vez que el usuario quiere escribir su propio monto/cantidad.
      prd_precio: [null as number | null, [Validators.required, Validators.min(0)]],
      prd_stock: [null as number | null, [Validators.required, Validators.min(0)]],
      prd_stkmin: [null as number | null, [Validators.required, Validators.min(0)]],
      prd_catid: [null as number | null, [Validators.required]],
      prd_marid: [null as number | null, [Validators.required]],
      prd_estado: [1, [Validators.required]],
    });
  }

  get esEdicion(): boolean {
    return !!this.producto?.prd_id;
  }

  /** Arma la URL completa hacia el backend para las imágenes ya guardadas,
   *  porque una ruta relativa como '/storage/...' se resolvería contra el
   *  origen de Angular (ej. localhost:4200) y no contra Laravel (127.0.0.1:8000). */
  obtenerUrlImagen(rutaRelativa: string): string {
    return `${environment.URL_BACKEND}/storage/${rutaRelativa}`;
  }

  ngOnChanges(): void {
    this.archivosSeleccionados = [];
    this.previews = [];
    this.error = null;

    if (this.producto) {
      this.form.patchValue({
        prd_titulo: this.producto.prd_titulo,
        prd_codinv: this.producto.prd_codinv,
        prd_descri: this.producto.prd_descri,
        prd_precio: this.producto.prd_precio,
        prd_stock: this.producto.prd_stock,
        prd_stkmin: this.producto.prd_stkmin,
        prd_catid: this.producto.prd_catid,
        prd_marid: this.producto.prd_marid,
        prd_estado: this.producto.prd_estado,
      });
    } else {
      this.form.reset({
        prd_titulo: '',
        prd_codinv: '',
        prd_descri: '',
        prd_precio: null,
        prd_stock: null,
        prd_stkmin: null,
        prd_catid: null,
        prd_marid: null,
        prd_estado: 1,
      });
    }
  }

  /** Se acumulan los archivos elegidos: cada vez que el usuario abre el selector
   *  se agregan a la lista existente en vez de reemplazarla, para poder subir varias
   *  imágenes por producto en distintas veces. */
  onArchivosSeleccionados(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files || !input.files.length) {
      return;
    }

    const nuevosArchivos = Array.from(input.files);
    this.archivosSeleccionados = [...this.archivosSeleccionados, ...nuevosArchivos];

    nuevosArchivos.forEach((archivo) => {
      const reader = new FileReader();
      reader.onload = () => this.previews.push(reader.result as string);
      reader.readAsDataURL(archivo);
    });

    // Se limpia el input para que, si el usuario vuelve a seleccionar el mismo
    // archivo más adelante, el evento "change" se dispare de nuevo igual.
    input.value = '';
  }

  /** Quita una imagen nueva (aún no subida) antes de guardar el producto. */
  quitarImagenNueva(index: number): void {
    this.archivosSeleccionados.splice(index, 1);
    this.previews.splice(index, 1);
  }

  quitarImagenExistente(imgId: number): void {
    if (!this.producto?.prd_id) {
      return;
    }

    this.productoService.eliminarImagen(this.producto.prd_id, imgId).subscribe({
      next: () => {
        this.producto!.imagenes = this.producto!.imagenes?.filter((i) => i.img_id !== imgId);
      },
      error: () => (this.error = 'No se pudo eliminar la imagen.'),
    });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.error = null;

    const formData = this.productoService.construirFormData(
      this.form.value as Record<string, any>,
      this.archivosSeleccionados
    );

    const request$ = this.esEdicion
      ? this.productoService.actualizar(this.producto!.prd_id!, formData)
      : this.productoService.crear(formData);

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