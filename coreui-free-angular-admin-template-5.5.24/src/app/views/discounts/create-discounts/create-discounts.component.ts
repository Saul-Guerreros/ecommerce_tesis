import { CommonModule } from '@angular/common';
import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute,Router, RouterLink } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { DiscountConfigItem } from '../models/discount.model';
import { DiscountsService } from '../service/discounts.service';

/** Fecha local en formato YYYY-MM-DD (toISOString usa UTC y en Paraguay puede dar "mañana"). */
export function fechaLocal(d: Date = new Date()): string {
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

@Component({
  selector: 'app-create-discounts',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, CardModule, ButtonModule, FormModule, TableModule, SpinnerModule],
  templateUrl: './create-discounts.component.html',
})
export class CreateDiscountsComponent implements OnInit {
  readonly esEdicion: boolean = false;
   private routeCreate = inject(ActivatedRoute);
     /** A dónde vuelven "Registrar" y "Cancelar": al listado o a la publicación de origen. */
  volverA: any[] = ['/discounts/list'];

  type_discount: number = 1; // 1 = porcentaje, 2 = monto fijo
  discount: number | null = null;
  type_campaing: number = 1; // 1 = por temporada, 2 = relámpago (1 día), 3 = por enlace
  discount_type: number = 1; // 1 = productos, 2 = categorías, 3 = marcas
  state: number = 1;
  product_id: any = null;
  categorie_id: any = null;
  brand_id: any = null;
  start_date: string = '';
  end_date: string = '';

  /** Fecha mínima permitida en "Desde". En edición se ajusta para campañas ya iniciadas. */
  minInicio: string = fechaLocal();

  isLoading$: any;
  guardando = false;

  categories_first: DiscountConfigItem[] = [];
  products: DiscountConfigItem[] = [];
  brands: DiscountConfigItem[] = [];

  categories_add: DiscountConfigItem[] = [];
  products_add: DiscountConfigItem[] = [];
  brands_add: DiscountConfigItem[] = [];

  readonly tiposCampania = [
    { valor: 1, etiqueta: 'Normal', ayuda: 'Se aplica automáticamente entre las fechas elegidas.' },
    { valor: 2, etiqueta: 'De un día', ayuda: 'Dura un solo día y se destaca en la tienda.' },
    // { valor: 3, etiqueta: 'Por link', ayuda: 'Solo la obtiene quien entra con el link compartido.' },
  ];

  constructor(
    public discountService: DiscountsService,
    protected router: Router,
    protected toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.isLoading$ = this.discountService.isLoading$;

    const params = this.routeCreate.snapshot.queryParamMap;
    const productoOrigen = Number(params.get('producto_id'));
    if (params.get('origen') === 'publicacion' && productoOrigen) {
      this.volverA = ['/publicaciones', productoOrigen];
    }

    this.discountService.configDiscounts().subscribe({
      next: (resp: any) => {
        this.categories_first = resp.categories ?? [];
        this.products = resp.products ?? [];
        this.brands = resp.brands ?? [];
        this.precargarProducto();
      },
      error: () => this.toastr.error('No se pudieron cargar las opciones', 'Error'),
    });
  }

  /** Si viene desde una publicación (?producto_id=X), deja el producto ya agregado. */
  private precargarProducto(): void {
    if (this.esEdicion) return;

    const id = Number(this.routeCreate.snapshot.queryParamMap.get('producto_id'));
    if (!id) return;

    const producto = this.products.find((p) => Number(p.id) === id);
    if (!producto) {
      this.toastr.warning('El producto no está disponible para campañas', 'Atención');
      return;
    }

    this.discount_type = 1; // Aplica a: Productos
    this.products_add = [producto];
  }

  get esRelampago(): boolean {
    return Number(this.type_campaing) === 2;
  }

  get ayudaCampania(): string {
    return this.tiposCampania.find((t) => t.valor === Number(this.type_campaing))?.ayuda ?? '';
  }
  
  get vieneDePublicacion(): boolean {
    return this.volverA[0] === '/publicaciones';
  }

  changeTypeDiscount(value: number): void {
    this.type_discount = value;
  }

  changeTypeCampaing(value: number): void {
    this.type_campaing = value;
    this.sincronizarFechas();
  }

  changeTypeCupone(value: number): void {
    this.discount_type = value;
    this.products_add = [];
    this.categories_add = [];
    this.brands_add = [];
    this.product_id = null;
    this.categorie_id = null;
    this.brand_id = null;
  }

  /** Llamado al cambiar "Desde" o el tipo de campaña. */
  sincronizarFechas(): void {
    if (this.esRelampago) {
      // Relámpago: un solo día → fin = inicio
      this.end_date = this.start_date;
      return;
    }
    // Si el fin quedó antes del inicio, se limpia para obligar a elegirlo de nuevo
    if (this.end_date && this.start_date && this.end_date < this.start_date) {
      this.end_date = '';
    }
  }

  /** Validación común a crear y editar. Devuelve el mensaje de error o null. */
  protected validar(): string | null {
    if (!this.discount || this.discount <= 0) return 'Ingresa un descuento mayor a 0';
    if (this.type_discount == 1 && this.discount > 100) return 'El porcentaje no puede superar 100';
    if (!this.start_date) return 'Selecciona la fecha de inicio';
    if (!this.end_date) return 'Selecciona la fecha de fin';
    // Las fechas YYYY-MM-DD se comparan bien como texto
    if (this.start_date < this.minInicio) return 'La fecha de inicio no puede ser anterior a hoy';
    if (this.end_date < this.start_date) return 'La fecha de fin no puede ser anterior a la de inicio';
    if (this.end_date < fechaLocal()) return 'La fecha de fin no puede estar en el pasado';
    if (this.esRelampago && this.end_date !== this.start_date) return 'Una oferta relámpago dura un solo día';
    if (this.discount_type == 1 && !this.products_add.length) return 'Agrega uno o varios productos';
    if (this.discount_type == 2 && !this.categories_add.length) return 'Agrega una o varias categorías';
    if (this.discount_type == 3 && !this.brands_add.length) return 'Agrega una o varias marcas';
    return null;
  }

  protected armarDatos(): any {
    return {
      type_discount: this.type_discount,
      discount_type: this.discount_type,
      discount: this.discount,
      product_selected: this.products_add,
      categorie_selected: this.categories_add,
      brand_selected: this.brands_add,
      start_date: this.start_date,
      end_date: this.end_date,
      type_campaing: this.type_campaing,
    };
  }

  save(): void {
    const error = this.validar();
    if (error) {
      this.toastr.warning(error, 'Atención');
      return;
    }

    this.guardando = true;
    this.discountService.createDiscounts(this.armarDatos()).subscribe({
      next: (resp: any) => this.manejarRespuesta(resp, 'Campaña registrada correctamente'),
      error: (err) => this.manejarError(err, 'No se pudo registrar la campaña'),
    });
  }

  protected manejarRespuesta(resp: any, exito: string): void {
    this.guardando = false;
    // Backend heredado: error de negocio con HTTP 200 + message 403
    if (resp?.message == 403) {
      this.toastr.error(resp.message_text ?? 'No se pudo guardar la campaña', 'Error');
      return;
    }
    this.toastr.success(exito, 'Éxito');
    this.router.navigate(this.volverA);
  }

  protected manejarError(err: any, porDefecto: string): void {
    this.guardando = false;
    const errors = err?.error?.errors;
    if (errors) {
      const primero = Object.values(errors)[0] as string[];
      if (primero?.length) {
        this.toastr.error(primero[0], 'Validación');
        return;
      }
    }
    this.toastr.error(err?.error?.message_text ?? err?.error?.message ?? porDefecto, 'Error');
  }

  addProduct(): void {
    this.agregar(this.product_id, this.products, this.products_add, 'producto');
    this.product_id = null;
  }

  addCategorie(): void {
    this.agregar(this.categorie_id, this.categories_first, this.categories_add, 'categoría');
    this.categorie_id = null;
  }

  addBrand(): void {
    this.agregar(this.brand_id, this.brands, this.brands_add, 'marca');
    this.brand_id = null;
  }

  removeProduct(item: DiscountConfigItem): void {
    this.products_add = this.products_add.filter((p) => p.id != item.id);
  }

  removeCategorie(item: DiscountConfigItem): void {
    this.categories_add = this.categories_add.filter((c) => c.id != item.id);
  }

  removeBrand(item: DiscountConfigItem): void {
    this.brands_add = this.brands_add.filter((b) => b.id != item.id);
  }

  private agregar(id: any, origen: DiscountConfigItem[], destino: DiscountConfigItem[], nombre: string): void {
    if (!id) {
      this.toastr.warning(`Selecciona un/a ${nombre}`, 'Atención');
      return;
    }
    if (destino.some((x) => x.id == id)) {
      this.toastr.warning(`Ese/a ${nombre} ya está en la lista`, 'Atención');
      return;
    }
    const item = origen.find((x) => x.id == id);
    if (item) destino.push(item);
  }
}