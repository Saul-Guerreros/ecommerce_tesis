import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
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
  code: any;
  type_discount: number = 1; // 1 = porcentaje, 2 = monto fijo
  discount: number = 0;
  type_count: number = 1; // 1 = ilimitado, 2 = limitado
  num_use: number = 0;
  type_cupone: number = 1; // 1 = productos, 2 = categorias, 3 = marcas
  product_id: any;
  categorie_id: any;
  brand_id: any;
  isLoading$: any;

  categories_first: CuponConfigItem[] = [];
  products: CuponConfigItem[] = [];
  brands: CuponConfigItem[] = [];

  categories_add: CuponConfigItem[] = [];
  products_add: CuponConfigItem[] = [];
  brands_add: CuponConfigItem[] = [];

  errorMsg = '';
  successMsg = '';

  constructor(public cuponesService: CuponesService, private router: Router) {}

  ngOnInit(): void {
    this.isLoading$ = this.cuponesService.isLoading$;
    this.cuponesService.configCupones().subscribe((resp: any) => {
      this.categories_first = resp.categories;
      this.products = resp.products;
      this.brands = resp.brands;
    });
  }

  changeTypeDiscount(value: number): void {
    this.type_discount = value;
  }

  changeTypeCount(value: number): void {
    this.type_count = value;
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
    this.errorMsg = '';
    this.successMsg = '';

    if (!this.code || !this.discount) {
      this.errorMsg = 'Necesitas llenar todos los campos';
      return;
    }

    if (this.type_count == 2 && this.num_use == 0) {
      this.errorMsg = 'Es necesario ponerle una cantidad de usos al cupon';
      return;
    }

    if (this.type_cupone == 1 && this.products_add.length == 0) {
      this.errorMsg = 'Es necesario seleccionar uno o varios productos';
      return;
    }

    if (this.type_cupone == 2 && this.categories_add.length == 0) {
      this.errorMsg = 'Es necesario seleccionar una o varias categorias';
      return;
    }

    if (this.type_cupone == 3 && this.brands_add.length == 0) {
      this.errorMsg = 'Es necesario seleccionar una o varias marcas';
      return;
    }

    const data = {
      type_discount: this.type_discount,
      type_count: this.type_count,
      type_cupone: this.type_cupone,
      num_use: this.num_use,
      discount: this.discount,
      code: this.code,
      product_selected: this.products_add,
      categorie_selected: this.categories_add,
      brand_selected: this.brands_add,
    };

    this.cuponesService.createCupones(data).subscribe((resp: any) => {
      if (resp.message == 403) {
        this.errorMsg = resp.message_text;
        return;
      }
      this.successMsg = 'El cupon se registro correctamente';
      setTimeout(() => this.router.navigate(['/cupones/list']), 800);
    });
  }

  addProduct(): void {
    if (!this.product_id) {
      this.errorMsg = 'Es necesario seleccionar un producto';
      return;
    }
    const INDEX = this.products_add.findIndex((prod) => prod.id == this.product_id);
    if (INDEX != -1) {
      this.errorMsg = 'El producto ya existe en la lista';
      return;
    }
    const DATA = this.products.find((product) => product.id == this.product_id);
    if (DATA) {
      this.products_add.push(DATA);
    }
    this.errorMsg = '';
  }

  addCategorie(): void {
    if (!this.categorie_id) {
      this.errorMsg = 'Es necesario seleccionar una categoria';
      return;
    }
    const INDEX = this.categories_add.findIndex((cat) => cat.id == this.categorie_id);
    if (INDEX != -1) {
      this.errorMsg = 'La categoria ya existe en la lista';
      return;
    }
    const DATA = this.categories_first.find((categorie) => categorie.id == this.categorie_id);
    if (DATA) {
      this.categories_add.push(DATA);
    }
    this.errorMsg = '';
  }

  addBrand(): void {
    if (!this.brand_id) {
      this.errorMsg = 'Es necesario seleccionar una marca';
      return;
    }
    const INDEX = this.brands_add.findIndex((brand) => brand.id == this.brand_id);
    if (INDEX != -1) {
      this.errorMsg = 'La marca ya existe en la lista';
      return;
    }
    const DATA = this.brands.find((brand) => brand.id == this.brand_id);
    if (DATA) {
      this.brands_add.push(DATA);
    }
    this.errorMsg = '';
  }

  removeProduct(product: CuponConfigItem): void {
    const INDEX = this.products_add.findIndex((prod) => prod.id == product.id);
    if (INDEX != -1) {
      this.products_add.splice(INDEX, 1);
    }
  }

  removeCategorie(categorie: CuponConfigItem): void {
    const INDEX = this.categories_add.findIndex((cat) => cat.id == categorie.id);
    if (INDEX != -1) {
      this.categories_add.splice(INDEX, 1);
    }
  }

  removeBrand(brand: CuponConfigItem): void {
    const INDEX = this.brands_add.findIndex((b) => b.id == brand.id);
    if (INDEX != -1) {
      this.brands_add.splice(INDEX, 1);
    }
  }
}
