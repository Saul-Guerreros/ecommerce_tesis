import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import {
  BadgeModule,
  ButtonModule,
  CardModule,
  FormModule,
  GridModule,
  SpinnerModule,
} from '@coreui/angular';
import { IconModule } from '@coreui/icons-angular';
import { forkJoin, of } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import { Categoria, Producto } from '../../../core/models/producto.model';
import { PublicacionWeb } from '../../../core/models/publicacion-web.model';
import { CatalogoService } from '../../../core/services/catalogo.service';
import { ProductoService } from '../../../core/services/producto.service';
import { ProductVariationsService } from '../../productos/product-variations/services/product-variations.service';
import { PublicacionWebService } from '../../../core/services/publicacion-web.service';
import { PreciosService } from '../../../modules/precios/service/precios.service';
import { Precio } from '../../../modules/precios/models/precio.model';

/**
 * Apartado "Publicación Web" de un producto: categoría, título y
 * descripción para la tienda, estado (borrador/publicado) e imágenes.
 * Vive separado del formulario de Producto (que solo tiene datos de
 * inventario/compra) porque son dos cosas distintas: un producto puede
 * existir en inventario sin estar todavía visible en la tienda.
 */
@Component({
  selector: 'app-publicacion-web',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    RouterLink,
    BadgeModule,
    ButtonModule,
    CardModule,
    FormModule,
    GridModule,
    SpinnerModule,
    IconModule,
  ],
  templateUrl: './publicacion-web.component.html',
})
export class PublicacionWebComponent implements OnInit {
  productoId!: number;
  producto: Producto | null = null;
  publicacion: PublicacionWeb | null = null;
  // Precio minorista/mayorista vigente del producto (Gestión de Precios).
  // Solo se usa para sugerir pub_prevta al crear una publicación nueva.
  precio: Precio | null = null;
  categorias: Categoria[] = [];

  cargando = false;
  guardando = false;
  error: string | null = null;
  mensaje: string | null = null;

  archivosNuevos: File[] = [];
  previews: string[] = [];

  form = this.fb.group({
    pub_catid: [null as number | null, [Validators.required]],
    pub_titulo: ['', [Validators.required, Validators.maxLength(150)]],
    // Precio propio de la ficha web: puede diferir del precio de inventario
    // del producto (permite ofertas o redondeo comercial sin tocar prd_precio).
    pub_prevta: [null as number | null, [Validators.min(0)]],
    // Si se deja vacío, el backend lo autogenera desde pub_titulo.
    pub_slug: [''],
    pub_descwb: [''],
    pub_meta_titulo: ['', [Validators.maxLength(160)]],
    pub_meta_descripcion: ['', [Validators.maxLength(320)]],
    pub_estado: [0, [Validators.required]], // nace como Borrador
    // Secciones manuales del home: un checkbox + orden por sección.
    featured_activo: [false],
    featured_orden: [null as number | null, [Validators.min(1), Validators.max(4)]],
    trending_activo: [false],
    trending_orden: [null as number | null, [Validators.min(1), Validators.max(4)]],
    // "Selling Products" es un placeholder MANUAL y TEMPORAL: no hay
    // módulo de Ventas real todavía, así que esto no refleja ventas
    // verdaderas. Se deja bien marcado en la etiqueta del template.
    selling_activo: [false],
    selling_orden: [null as number | null, [Validators.min(1), Validators.max(4)]],
  });

  // Nombres de sección tal como los espera el backend (vitrina_producto.vip_seccion).
  readonly secciones = ['featured', 'trending', 'selling'] as const;

  constructor(
    private fb: FormBuilder,
    private route: ActivatedRoute,
    private productoService: ProductoService,
    private publicacionService: PublicacionWebService,
    private catalogoService: CatalogoService,
	private variationsService: ProductVariationsService,
    private preciosService: PreciosService,
  ) {}

  ngOnInit(): void {
    this.productoId = Number(this.route.snapshot.paramMap.get('id'));
    this.catalogoService.categorias().subscribe((data) => (this.categorias = data));
    this.cargarDatos();
  }

  get esNueva(): boolean {
    return !this.publicacion?.pub_id;
  }

  badgeEstado(estado: number): { texto: string; color: string } {
    switch (estado) {
      case 1:
        return { texto: 'Publicado', color: 'success' };
      case 2:
        return { texto: 'Archivado', color: 'dark' };
      default:
        return { texto: 'Borrador', color: 'secondary' };
    }
  }
  
  copiando = false;

get puedeCopiarDelPadre(): boolean {
  return this.esNueva && !!this.producto?.prd_padre_id;
}

copiarDelPadre(): void {
  if (!this.producto?.prd_padre_id) return;
  this.copiando = true;

  this.publicacionService.obtener(this.producto.prd_padre_id).subscribe({
    next: (res) => {
      const origen = res.data;
      if (origen) {
        this.form.patchValue({
          pub_catid: origen.pub_catid,
          pub_titulo: origen.pub_titulo, // solo hay que agregarle el color, ej: "... - Rojo"
          pub_descwb: origen.pub_descwb,
          pub_meta_titulo: origen.pub_meta_titulo,
          pub_meta_descripcion: origen.pub_meta_descripcion,
        });
      }
      this.copiando = false;
    },
    error: () => (this.copiando = false),
  });
}
  

  // El botón de guardar refleja lo que el Estado seleccionado va a hacer
  // realmente al guardar — antes decía "Publicar producto" sin importar
  // el Estado elegido, lo cual llevaba a guardar como Borrador creyendo
  // que había quedado publicado.
  get textoBotonGuardar(): string {
    return this.form.value.pub_estado === 1 ? 'Publicar producto' : 'Guardar como borrador';
  }

  seccionActiva(seccion: 'featured' | 'trending' | 'selling'): boolean {
    return !!this.form.value[`${seccion}_activo` as const];
  }

  onToggleSeccion(seccion: 'featured' | 'trending' | 'selling', activado: boolean): void {
    const ordenControl = `${seccion}_orden` as const;
    this.form.patchValue({
      [`${seccion}_activo`]: activado,
      // Al tildar sin un orden previo, se asume "1" (primero) como default
      // razonable; el admin lo puede cambiar en el campo de al lado.
      [ordenControl]: activado ? this.form.value[ordenControl] || 1 : null,
    });
  }

  cargarDatos(): void {
    this.cargando = true;
    this.error = null;

    // Los 3 vienen de módulos distintos (Producto, Publicación Web y
    // Gestión de Precios) y se combinan recién acá. El precio se pide con
    // catchError -> of(null): un producto sin compras/precio cargado
    // todavía no debe tumbar el resto de la pantalla.
    forkJoin({
      producto: this.productoService.obtener(this.productoId),
      publicacion: this.publicacionService.obtener(this.productoId),
      precio: this.preciosService.getOne(this.productoId).pipe(catchError(() => of(null))),
    }).subscribe({
      next: ({ producto, publicacion, precio }) => {
        this.producto = producto.data;
        this.publicacion = publicacion.data;
        this.precio = precio;

        if (this.publicacion) {
          // Ya existe: se respetan los valores guardados, no se pisan con
          // los actuales del producto (pub_titulo/pub_prevta pudieron
          // editarse a propósito para diferir del producto).
          this.form.patchValue({
            pub_catid: this.publicacion.pub_catid,
            pub_titulo: this.publicacion.pub_titulo,
            pub_prevta: this.publicacion.pub_prevta,
            pub_slug: this.publicacion.pub_slug ?? '',
            pub_descwb: this.publicacion.pub_descwb ?? '',
            pub_meta_titulo: this.publicacion.pub_meta_titulo ?? '',
            pub_meta_descripcion: this.publicacion.pub_meta_descripcion ?? '',
            pub_estado: this.publicacion.pub_estado,
            featured_activo: this.publicacion.vitrina?.featured != null,
            featured_orden: this.publicacion.vitrina?.featured ?? null,
            trending_activo: this.publicacion.vitrina?.trending != null,
            trending_orden: this.publicacion.vitrina?.trending ?? null,
            selling_activo: this.publicacion.vitrina?.selling != null,
            selling_orden: this.publicacion.vitrina?.selling ?? null,
          });
        } else if (this.producto) {
          // Publicación nueva: se sugieren título (nombre del producto) y
          // precio de venta (minorista de Gestión de Precios). El admin
          // los puede editar antes de guardar, igual que cualquier otro
          // campo del formulario.
          this.form.patchValue({
            pub_titulo: this.producto.prd_descri,
            pub_prevta: this.precio?.minorista?.precio ?? null,
          });
        }

        this.cargando = false;
      },
      error: () => {
        this.error = 'No se pudo cargar el producto o la publicación web.';
        this.cargando = false;
      },
    });
  }

  onArchivosSeleccionados(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files) {
      return;
    }

    Array.from(input.files).forEach((archivo) => {
      this.archivosNuevos.push(archivo);
      const lector = new FileReader();
      lector.onload = () => this.previews.push(lector.result as string);
      lector.readAsDataURL(archivo);
    });

    input.value = '';
  }

  quitarImagenNueva(index: number): void {
    this.archivosNuevos.splice(index, 1);
    this.previews.splice(index, 1);
  }

  quitarImagenExistente(imagenId: number): void {
    this.publicacionService.eliminarImagen(this.productoId, imagenId).subscribe({
      next: () => {
        if (this.publicacion?.imagenes) {
          this.publicacion.imagenes = this.publicacion.imagenes.filter((i) => i.img_id !== imagenId);
        }
      },
      error: () => (this.error = 'No se pudo eliminar la imagen.'),
    });
  }

  obtenerUrlImagen(url: string): string {
    return `${environment.URL_BACKEND}/storage/${url}`;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

	const eraNueva = this.esNueva; // capturarlo ANTES de cargarDatos(), que lo cambia a false
    this.guardando = true;
    this.error = null;
    this.mensaje = null;

    const formData = this.publicacionService.construirFormData(this.form.value, this.archivosNuevos);

    this.publicacionService.guardar(this.productoId, formData).subscribe({
      next: (res) => {
        this.guardando = false;
        this.mensaje = res.message ?? 'Guardado correctamente.';
        this.archivosNuevos = [];
        this.previews = [];
        this.cargarDatos();
		
		// Primera vez que se publica ESTA variante y tiene un modelo padre:
      // copiamos sus especificaciones automáticamente (RAM, procesador, etc.)
		if (eraNueva && this.producto?.prd_padre_id) {
			this.variationsService
          .duplicarEspecificaciones(this.producto.prd_padre_id, this.productoId)
          .subscribe();
		}
      },
      error: (err) => {
        this.guardando = false;
        this.error = err?.error?.message ?? 'No se pudo guardar la publicación.';
      },
    });
  }
}
