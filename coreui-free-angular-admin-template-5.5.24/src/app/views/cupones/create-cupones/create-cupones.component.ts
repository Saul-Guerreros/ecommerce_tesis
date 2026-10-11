import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { CuponConfigItem } from '../models/cupon.model';
import { CuponesService } from '../service/cupones.service';

@Component({
  selector: 'app-create-cupones',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, CardModule, ButtonModule, FormModule, TableModule, SpinnerModule],
  templateUrl: './create-cupones.component.html',
})
export class CreateCuponesComponent implements OnInit {
    readonly esEdicion: boolean = false;

  code: string = '';
  type_discount: number = 1; // 1 = porcentaje, 2 = monto fijo
  discount: number | null = null;
  type_count: number = 1; // 1 = ilimitado, 2 = limitado
  num_use: number | null = null;
  type_cupone: number = 1; // 1 = productos, 2 = categorías, 3 = marcas
  state: number = 1;
  product_id: any = null;
  categorie_id: any = null;
  brand_id: any = null;
  isLoading$: any;
  guardando = false;

  categories_first: CuponConfigItem[] = [];
  products: CuponConfigItem[] = [];
  brands: CuponConfigItem[] = [];

  categories_add: CuponConfigItem[] = [];
  products_add: CuponConfigItem[] = [];
  brands_add: CuponConfigItem[] = [];

  constructor(
    public cuponesService: CuponesService,
    private router: Router,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.isLoading$ = this.cuponesService.isLoading$;
    this.cuponesService.configCupones().subscribe({
      next: (resp: any) => {
        this.categories_first = resp.categories ?? [];
        this.products = resp.products ?? [];
        this.brands = resp.brands ?? [];
      },
      error: () => this.toastr.error('No se pudieron cargar las opciones', 'Error'),
    });
  }

  changeTypeDiscount(value: number): void {
    this.type_discount = value;
  }

  changeTypeCount(value: number): void {
    this.type_count = value;
    if (value === 1) this.num_use = null;
  }

  changeTypeCupone(value: number): void {
    this.type_cupone = value;
    this.products_add = [];
    this.categories_add = [];
    this.brands_add = [];
    this.product_id = null;
    this.categorie_id = null;
    this.brand_id = null;
  }

  save(): void {
    if (!this.code?.trim() || !this.discount) {
      this.toastr.warning('Completa el código y el descuento', 'Atención');
      return;
    }
    if (this.type_discount == 1 && this.discount > 100) {
      this.toastr.warning('El porcentaje no puede superar 100', 'Atención');
      return;
    }
    if (this.type_count == 2 && !this.num_use) {
      this.toastr.warning('Indica la cantidad de usos del cupón', 'Atención');
      return;
    }
    if (this.type_cupone == 1 && this.products_add.length == 0) {
      this.toastr.warning('Agrega uno o varios productos', 'Atención');
      return;
    }
    if (this.type_cupone == 2 && this.categories_add.length == 0) {
      this.toastr.warning('Agrega una o varias categorías', 'Atención');
      return;
    }
    if (this.type_cupone == 3 && this.brands_add.length == 0) {
      this.toastr.warning('Agrega una o varias marcas', 'Atención');
      return;
    }

    const data = {
      type_discount: this.type_discount,
      type_count: this.type_count,
      type_cupone: this.type_cupone,
      num_use: this.type_count == 2 ? this.num_use : 0,
      discount: this.discount,
      code: this.code.trim(),
      product_selected: this.products_add,
      categorie_selected: this.categories_add,
      brand_selected: this.brands_add,
    };

    this.guardando = true;
    this.cuponesService.createCupones(data).subscribe({
      next: (resp: any) => {
        this.guardando = false;
        if (resp?.message == 403) {
          this.toastr.error(resp.message_text ?? 'No se pudo registrar el cupón', 'Error');
          return;
        }
        this.toastr.success('Cupón registrado correctamente', 'Éxito');
        this.router.navigate(['/cupones/list']);
      },
      error: (err) => {
        this.guardando = false;
        this.toastr.error(this.mensajeError(err, 'No se pudo registrar el cupón'), 'Error');
      },
    });
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

  removeProduct(item: CuponConfigItem): void {
    this.products_add = this.products_add.filter((p) => p.id != item.id);
  }

  removeCategorie(item: CuponConfigItem): void {
    this.categories_add = this.categories_add.filter((c) => c.id != item.id);
  }

  removeBrand(item: CuponConfigItem): void {
    this.brands_add = this.brands_add.filter((b) => b.id != item.id);
  }

  /** Lógica común de agregar a la lista (evita repetir 3 veces lo mismo). */
  private agregar(id: any, origen: CuponConfigItem[], destino: CuponConfigItem[], nombre: string): void {
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

  private mensajeError(err: any, porDefecto: string): string {
    const errors = err?.error?.errors;
    if (errors) {
      const primero = Object.values(errors)[0] as string[];
      if (primero?.length) return primero[0];
    }
    return err?.error?.message_text ?? err?.error?.message ?? porDefecto;
  }
}