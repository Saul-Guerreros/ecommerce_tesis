import { CommonModule } from '@angular/common';
import { Component, OnInit, ViewChild, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import {BadgeModule,ButtonModule,CardModule,FormModule,GridModule,SpinnerModule,} from '@coreui/angular';
import { IconModule } from '@coreui/icons-angular';
import { forkJoin, of } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import { Categoria, Producto } from '../../../core/models/producto.model';
import { PublicacionWeb } from '../../../core/models/publicacion-web.model';
import { CatalogoService } from '../../../core/services/catalogo.service';
import { ProductoService } from '../../../core/services/producto.service';
import { PublicacionWebService } from '../../../core/services/publicacion-web.service';
import { PreciosService } from '../../../modules/precios/service/precios.service';
import { Precio } from '../../../modules/precios/models/precio.model';
import { ConfiguracionTiendaService } from '../../../modules/configuracion-tienda/service/configuracion-tienda.service';
import { ConfiguracionTienda } from '../../../modules/configuracion-tienda/models/configuracion-tienda.model';
import { FichaTecnicaFormComponent } from '../../../modules/ficha-tecnica/ficha-tecnica-form/ficha-tecnica-form.component';
import { ToastrService } from 'ngx-toastr';
import { DiscountsService } from '../../discounts/service/discounts.service';
import { PublicacionBorradorService } from './publicacion-borrador.service';
import { ValorFicha } from '../../../modules/ficha-tecnica/models/ficha-tecnica.model';

type OrigenUmbral = 'general' | 'individual';
/**
 * Apartado "Publicación Web" de un producto: categoría, título y
 * descripción para la tienda, estado (borrador/publicado) e imágenes.
 * Vive separado del formulario de Producto (que solo tiene datos de
 * inventario/compra) porque son dos cosas distintas: un producto puede
 * existir en inventario sin estar todavía visible en la tienda.
 */
/** Debe coincidir con VitrinaProducto::MAX_ORDEN del backend. */
	const MAX_ORDEN_VITRINA = 12;
 
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
    FichaTecnicaFormComponent,
  ],
  templateUrl: './publicacion-web.component.html',
})
export class PublicacionWebComponent implements OnInit {
  private fb = inject(FormBuilder); // ← nuevo: se resuelve acá mismo
  private discountsService = inject(DiscountsService);
  private borradorService = inject(PublicacionBorradorService);

  /** Campañas activas o programadas que alcanzan a este producto. */
  campanias: any[] = [];
  /** Valores de ficha a restaurar desde el borrador. */
  fichaInicial: ValorFicha[] | null = null;

  readonly nombresCampania: Record<number, string> = { 1: 'Normal', 2: 'De un día', 3: 'Por link' };
  readonly nombresAlcance: Record<number, string> = { 1: 'este producto', 2: 'su categoría', 3: 'su marca' };
  

  productoId!: number;
  producto: Producto | null = null;
  publicacion: PublicacionWeb | null = null;
  // Precios vigentes del producto (Gestión de Precios). SOLO LECTURA: se
  // muestran para que el admin sepa a cuánto va a salir el producto en la
  // tienda, pero el dueño del dato es `producto`, no la ficha web.

  precio: Precio | null = null;
  // Umbral general vigente en Configuración de la Tienda. Solo se usa para
  // mostrarlo en la etiqueta "Usar umbral general (X un.)"; el override en
  // sí vive en pub_pocstk_umbral.
  configuracionTienda: ConfiguracionTienda | null = null;
  categorias: Categoria[] = [];

  @ViewChild(FichaTecnicaFormComponent) fichaTecnica?: FichaTecnicaFormComponent;
  /** Errores 422 de la ficha ("ficha.{atb_id}"), se muestran junto a cada campo. */
  erroresFicha: Record<string, string> = {};
  /** Errores 422 de los campos de la publicación (ej. pub_catid). */
  erroresBackend: Record<string, string> = {};

  cargando = false;
  guardando = false;
  error: string | null = null;
  mensaje: string | null = null;

  archivosNuevos: File[] = [];
  previews: string[] = [];

  form = this.fb.group({
    pub_catid: [null as number | null, [Validators.required]],
    pub_titulo: ['', [Validators.required, Validators.maxLength(150)]],
    // Si se deja vacío, el backend lo autogenera desde pub_titulo.
    pub_slug: [''],
    pub_descwb: [''],
    pub_meta_titulo: ['', [Validators.maxLength(160)]],
    pub_meta_descripcion: ['', [Validators.maxLength(320)]],

        pub_estado: [0, [Validators.required]], // nace como Borrador
    // Control UI, NO se manda tal cual al backend (ver guardar()): decide
    // si pub_pocstk_umbral viaja null (general) o con el valor individual.
    origenUmbralStock: ['general' as OrigenUmbral],
    valorUmbralStock: [null as number | null, [Validators.min(1), Validators.max(1000)]],

    // Secciones manuales del home: un checkbox + orden por sección.
    featured_activo: [false],
    featured_orden: [null as number | null, [Validators.min(1), Validators.max(MAX_ORDEN_VITRINA)]],
    trending_activo: [false],
     trending_orden: [null as number | null, [Validators.min(1), Validators.max(MAX_ORDEN_VITRINA)]],
    // "Selling Products" es un placeholder MANUAL y TEMPORAL: no hay
    // módulo de Ventas real todavía, así que esto no refleja ventas
    // verdaderas. Se deja bien marcado en la etiqueta del template.
    selling_activo: [false],
    selling_orden: [null as number | null, [Validators.min(1), Validators.max(MAX_ORDEN_VITRINA)]],
  });

  // Nombres de sección tal como los espera el backend (vitrina_producto.vip_seccion).
  readonly secciones = ['featured', 'trending', 'selling'] as const;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private toastr: ToastrService,
    private productoService: ProductoService,
    private publicacionService: PublicacionWebService,
    private catalogoService: CatalogoService,
    private preciosService: PreciosService,
    private configuracionTiendaService: ConfiguracionTiendaService,
  ) {}

  ngOnInit(): void {
    this.productoId = Number(this.route.snapshot.paramMap.get('id'));
    this.catalogoService.categorias().subscribe((data) => (this.categorias = data));
    this.cargarDatos();
	this.cargarCampanias();
  }

  /**
   * Solo subcategorías, agrupadas por su categoría principal: la ficha
   * técnica (y el buscador) dependen de la plantilla de la subcategoría.
   */
  get gruposCategorias(): { padre: string; hijas: Categoria[] }[] {
    const raices = this.categorias.filter((c) => !c.cat_idpadr);

    return raices
      .map((raiz) => ({
        padre: raiz.cat_nombre,
        hijas: this.categorias.filter((c) => c.cat_idpadr === raiz.cat_id),
      }))
      .filter((grupo) => grupo.hijas.length > 0);
  }

  get esVariante(): boolean {
    return !!this.producto?.prd_padre_id;
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

    /** Para la etiqueta "Usar umbral general (X un.)". */
  get umbralGeneralStock(): number | null {
    return this.configuracionTienda?.ctn_pocstk_umbral ?? null;
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

    // Los 3 vienen de módulos distintos (Producto, Publicación Web y
    // Gestión de Precios) y se combinan recién acá. El precio se pide con
    // catchError -> of(null): un producto sin compras/precio cargado
    // todavía no debe tumbar el resto de la pantalla.
       forkJoin({
      producto: this.productoService.obtener(this.productoId),
      publicacion: this.publicacionService.obtener(this.productoId),
      precio: this.preciosService.getOne(this.productoId).pipe(catchError(() => of(null))),
      configuracionTienda: this.configuracionTiendaService.getConfig().pipe(catchError(() => of(null))),
    }).subscribe({
      next: ({ producto, publicacion, precio, configuracionTienda }) => {
        this.producto = producto.data;
        this.publicacion = publicacion.data;
        this.precio = precio;
        this.configuracionTienda = configuracionTienda;

        if (this.publicacion) {
          // Ya existe: se respetan los valores guardados, no se pisan con
          // los actuales del producto (pub_titulo pudo editarse a propósito
          // para diferir del nombre de inventario).
          this.form.patchValue({
            pub_catid: this.publicacion.pub_catid,
            pub_titulo: this.publicacion.pub_titulo,
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
            origenUmbralStock: this.publicacion.pub_pocstk_umbral != null ? 'individual' : 'general',
            valorUmbralStock: this.publicacion.pub_pocstk_umbral ?? null,
          });

        } else if (this.producto) {
          // Publicación nueva: se sugiere el título con el nombre del
          // producto. El precio ya no se sugiere porque ya no se guarda
          // acá — sale directo de Gestión de Precios.
          this.form.patchValue({
            pub_titulo: this.producto.prd_descri,
          });
        }

        this.cargando = false;
		this.aplicarBorrador();
      },
      error: () => {
        this.toastr.error('No se pudo cargar el producto o la publicación web', 'Error');
        this.cargando = false;
      },
    });
  }

    cargarCampanias(): void {
    this.discountsService.vigentesPorProducto(this.productoId).subscribe({
      next: (res: any) => (this.campanias = res.data ?? []),
      error: () => (this.campanias = []),
    });
  }

    /** Guarda lo que se está editando y va a crear la campaña con el producto precargado. */
  irACrearCampania(): void {
    this.guardarBorrador();
    this.router.navigate(['/discounts/register'], {
      queryParams: { producto_id: this.productoId, origen: 'publicacion' },
    });
  }

  /** Igual que crear: guarda el borrador y abre la campaña existente con retorno a esta publicación. */
  irAVerCampania(campaniaId: number): void {
    this.guardarBorrador();
    this.router.navigate(['/discounts/list/edit', campaniaId], {
      queryParams: { producto_id: this.productoId, origen: 'publicacion' },
    });
  }

  private guardarBorrador(): void {
    this.borradorService.guardar(this.productoId, {
      formValue: this.form.getRawValue(),
      archivosNuevos: [...this.archivosNuevos],
      previews: [...this.previews],
      ficha: this.fichaTecnica?.listo ? this.fichaTecnica.valores() : null,
    });
  }

  /** Si se volvió desde otro módulo, restaura lo que no se había guardado. */
  private aplicarBorrador(): void {
    const borrador = this.borradorService.tomar(this.productoId);
    if (!borrador) return;

    this.form.patchValue(borrador.formValue);
    this.archivosNuevos = borrador.archivosNuevos;
    this.previews = borrador.previews;
    this.fichaInicial = borrador.ficha;
    this.form.markAsDirty();

    this.toastr.info('Se recuperaron los cambios que aún no habías guardado', 'Borrador');
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
      next: (res) => {
        if (this.publicacion?.imagenes) {
          this.publicacion.imagenes = this.publicacion.imagenes.filter((i) => i.img_id !== imagenId);
        }
        this.toastr.success(res?.message ?? 'Imagen eliminada', 'Éxito');
      },
      error: () => this.toastr.error('No se pudo eliminar la imagen', 'Error'),
    });
  }

  obtenerUrlImagen(url: string): string {
    return `${environment.URL_BACKEND}/storage/${url}`;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toastr.error(`Revisá estos campos: ${this.camposInvalidos().join(', ')}.`, 'Validación');
      return;
    }

    this.guardando = true;

    const { origenUmbralStock, valorUmbralStock, ...valoresForm } = this.form.value;
    const payload: Record<string, any> = {
      ...valoresForm,
      pub_pocstk_umbral: origenUmbralStock === 'individual' ? valorUmbralStock : null,
    };

    const ficha = this.fichaTecnica;
    if (ficha?.listo) {
      const faltantes = ficha.faltantes();
      if (valoresForm.pub_estado === 1 && faltantes.length) {
        ficha.marcarFaltantes();
        this.guardando = false;
        this.toastr.error(`Para publicar, completá la ficha técnica: ${faltantes.join(', ')}.`, 'Validación');
        return;
      }
      payload['ficha'] = JSON.stringify(ficha.valores());
    }
    this.erroresFicha = {};
    this.erroresBackend = {};

    const formData = this.publicacionService.construirFormData(payload, this.archivosNuevos);

    this.publicacionService.guardar(this.productoId, formData).subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message ?? 'Publicación guardada correctamente', 'Éxito');
        this.router.navigate(['/publicaciones']);
      },
      error: (err) => this.handleError(err),
    });
  }
  
    /** Disponible en la plantilla para el atributo max y los mensajes. */
  readonly maxOrdenVitrina = MAX_ORDEN_VITRINA;

  private readonly etiquetasCampos: Record<string, string> = {
    pub_catid: 'Categoría',
    pub_titulo: 'Título',
    pub_slug: 'URL amigable',
    pub_meta_titulo: 'Meta título',
    pub_meta_descripcion: 'Meta descripción',
    pub_estado: 'Estado',
    valorUmbralStock: 'Umbral de poco stock',
    featured_orden: 'Orden en Destacados',
    trending_orden: 'Orden en Tendencia',
    selling_orden: 'Orden en Más vendidos',
  };

  /** Nombres legibles de los campos inválidos del formulario. */
  private camposInvalidos(): string[] {
    return Object.entries(this.form.controls)
      .filter(([, control]) => control.invalid)
      .map(([nombre]) => this.etiquetasCampos[nombre] ?? nombre);
  }

  private handleError(err: any): void {
    this.guardando = false;

    if (err?.status === 422 && err.error?.errors) {
      const errores: Record<string, string[]> = err.error.errors;
      const generales: string[] = [];

      Object.entries(errores).forEach(([campo, mensajes]) => {
        if (campo.startsWith('ficha.')) {
          this.erroresFicha[campo] = mensajes[0];
        } else if (campo === 'pub_catid') {
          this.erroresBackend[campo] = mensajes[0];
        } else {
          generales.push(mensajes[0]);
        }
      });

      this.toastr.error(
        generales.length ? generales.join(' ') : 'Revise los datos marcados en el formulario',
        'Validación'
      );
      return;
    }

    this.toastr.error(err?.error?.message ?? 'No se pudo guardar la publicación', 'Error');
  }
}