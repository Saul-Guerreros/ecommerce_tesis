import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { DiscountConfigItem } from '../models/discount.model';
import { DiscountsService } from '../service/discounts.service';

@Component({
  selector: 'app-edit-discounts',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, CardModule, ButtonModule, FormModule, TableModule, SpinnerModule],
  templateUrl: './edit-discounts.component.html',
})
export class EditDiscountsComponent implements OnInit {
  type_discount: number = 1;
  discount: number = 0;
  type_campaing: number = 1;
  discount_type: number = 1;
  product_id: any;
  categorie_id: any;
  brand_id: any;
  start_date: any;
  end_date: any;
  state: number = 1;

  isLoading$: any;

  categories_first: DiscountConfigItem[] = [];
  products: DiscountConfigItem[] = [];
  brands: DiscountConfigItem[] = [];

  categories_add: DiscountConfigItem[] = [];
  products_add: DiscountConfigItem[] = [];
  brands_add: DiscountConfigItem[] = [];

  errorMsg = '';
  successMsg = '';

  DISCOUNT_ID: any;

  constructor(
    public discountService: DiscountsService,
    private activedRoute: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.isLoading$ = this.discountService.isLoading$;

    this.discountService.configDiscounts().subscribe((resp: any) => {
      this.categories_first = resp.categories;
      this.products = resp.products;
      this.brands = resp.brands;
    });

    this.activedRoute.params.subscribe((resp: any) => {
      this.DISCOUNT_ID = resp.id;

      this.discountService.showDiscount(this.DISCOUNT_ID).subscribe((resp: any) => {
        this.type_discount = resp.discount.type_discount;
        this.discount_type = resp.discount.discount_type;
        this.discount = resp.discount.discount;
        this.start_date = resp.discount.start_date;
        this.end_date = resp.discount.end_date;
        this.type_campaing = resp.discount.type_campaing;
        this.state = resp.discount.state;

        this.categories_add = resp.discount.categories;
        this.products_add = resp.discount.products;
        this.brands_add = resp.discount.brands;
      });
    });
  }

  changeTypeDiscount(value: number): void {
    this.type_discount = value;
  }

  changeTypeCampaing(value: number): void {
    this.type_campaing = value;
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

  save(): void {
    this.errorMsg = '';
    this.successMsg = '';

    if (!this.discount || !this.start_date || !this.end_date) {
      this.errorMsg = 'Necesitas llenar todos los campos';
      return;
    }

    if (this.discount_type == 1 && this.products_add.length == 0) {
      this.errorMsg = 'Es necesario seleccionar uno o varios productos';
      return;
    }

    if (this.discount_type == 2 && this.categories_add.length == 0) {
      this.errorMsg = 'Es necesario seleccionar una o varias categorias';
      return;
    }

    if (this.discount_type == 3 && this.brands_add.length == 0) {
      this.errorMsg = 'Es necesario seleccionar una o varias marcas';
      return;
    }

    const data = {
      type_discount: this.type_discount,
      discount_type: this.discount_type,
      discount: this.discount,
      product_selected: this.products_add,
      categorie_selected: this.categories_add,
      brand_selected: this.brands_add,
      start_date: this.start_date,
      end_date: this.end_date,
      type_campaing: this.type_campaing,
      state: this.state,
    };

    this.discountService.updateDiscounts(this.DISCOUNT_ID, data).subscribe((resp: any) => {
      if (resp.message == 403) {
        this.errorMsg = resp.message_text;
        return;
      }
      this.successMsg = 'La campaña se edito correctamente';
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

  removeProduct(product: DiscountConfigItem): void {
    const INDEX = this.products_add.findIndex((prod) => prod.id == product.id);
    if (INDEX != -1) {
      this.products_add.splice(INDEX, 1);
    }
  }

  removeCategorie(categorie: DiscountConfigItem): void {
    const INDEX = this.categories_add.findIndex((cat) => cat.id == categorie.id);
    if (INDEX != -1) {
      this.categories_add.splice(INDEX, 1);
    }
  }

  removeBrand(brand: DiscountConfigItem): void {
    const INDEX = this.brands_add.findIndex((b) => b.id == brand.id);
    if (INDEX != -1) {
      this.brands_add.splice(INDEX, 1);
    }
  }
}
