import { environment } from '../../../../environments/environment';
import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import {
  BadgeModule,
  ButtonModule,
  CardModule,
  FormModule,
  ModalModule,
  SpinnerModule,
  TableModule,
  PaginationModule,
} from '@coreui/angular';
import { Discount } from '../models/discount.model';
import { DiscountsService } from '../service/discounts.service';

@Component({
  selector: 'app-list-discounts',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterLink,
    CardModule,
    TableModule,
    ButtonModule,
    FormModule,
    ModalModule,
    BadgeModule,
    SpinnerModule,
	PaginationModule,
  ],
  templateUrl: './list-discounts.component.html',
})
export class ListDiscountsComponent implements OnInit {
  discounts: Discount[] = [];
  search = '';
  total = 0;
  currentPage = 1;
  pageSize = 25;

  isLoading$: any;

  deleteModalVisible = false;
  discountToDelete: Discount | null = null;
  isDeleting = false;

  // Ajusta a la URL real de tu tienda para el link publico de "Campaña Link"
  urlTienda = environment.URL_FRONTEND;

  constructor(public discountService: DiscountsService) {}

  ngOnInit(): void {
    this.isLoading$ = this.discountService.isLoading$;
    this.listDiscounts();
  }

  get totalPages(): number {
    return Math.max(1, Math.ceil(this.total / this.pageSize));
  }

  listDiscounts(page: number = 1): void {
    this.discountService.listDiscounts(page, this.search).subscribe((resp: any) => {
      this.discounts = resp.discounts.data;
      this.total = resp.total;
      this.currentPage = page;
    });
  }

  searchTo(): void {
    this.listDiscounts(1);
  }

  loadPage(page: number): void {
    if (page < 1 || page > this.totalPages) {
      return;
    }
    this.listDiscounts(page);
  }

  getNameTypeDiscount(discount_type: number): string {
    switch (discount_type) {
      case 1: return 'Productos';
      case 2: return 'Categorias';
      case 3: return 'Marcas';
      default: return '';
    }
  }

  getNameTypeCampaing(type_campaing: number): string {
    switch (type_campaing) {
      case 1: return 'Campaña normal';
      case 2: return 'Campaña flash';
      case 3: return 'Campaña link';
      default: return '';
    }
  }

  copyLink(discount: Discount): void {
    const aux = document.createElement('input');
    aux.setAttribute('value', `${this.urlTienda}/discount/${discount.code}`);
    document.body.appendChild(aux);
    aux.select();
    document.execCommand('copy');
    document.body.removeChild(aux);
  }

  confirmDelete(discount: Discount): void {
    this.discountToDelete = discount;
    this.deleteModalVisible = true;
  }

  cancelDelete(): void {
    this.deleteModalVisible = false;
    this.discountToDelete = null;
  }

  deleteDiscount(): void {
    if (!this.discountToDelete) {
      return;
    }
    this.isDeleting = true;
    this.discountService.deleteDiscount(this.discountToDelete.id as number).subscribe(() => {
      const index = this.discounts.findIndex((d) => d.id === this.discountToDelete!.id);
      if (index !== -1) {
        this.discounts.splice(index, 1);
      }
      this.isDeleting = false;
      this.deleteModalVisible = false;
      this.discountToDelete = null;
    });
  }
}
