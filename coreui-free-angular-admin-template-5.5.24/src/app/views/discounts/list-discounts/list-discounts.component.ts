import { environment } from '../../../../environments/environment';
import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  BadgeModule,
  ButtonModule,
  CardModule,
  FormModule,
  ModalModule,
  PaginationModule,
  SpinnerModule,
  TableModule,
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

  urlTienda = environment.URL_FRONTEND;

  constructor(
    public discountService: DiscountsService,
    private router: Router,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.isLoading$ = this.discountService.isLoading$;
    this.listDiscounts();
  }

  get totalPages(): number {
    return Math.max(1, Math.ceil(this.total / this.pageSize));
  }

  get pagesArray(): number[] {
    return Array.from({ length: this.totalPages }, (_, i) => i + 1);
  }

  listDiscounts(page: number = 1): void {
    this.discountService.listDiscounts(page, this.search).subscribe({
      next: (resp: any) => {
        this.discounts = resp.discounts.data;
        this.total = resp.total;
        this.currentPage = page;
      },
      error: () => this.toastr.error('No se pudieron cargar las campañas', 'Error'),
    });
  }

  searchTo(): void {
    this.listDiscounts(1);
  }

  loadPage(page: number): void {
    if (page < 1 || page > this.totalPages) return;
    this.listDiscounts(page);
  }

  getNameTypeDiscount(discount_type: number): string {
    switch (Number(discount_type)) {
      case 1: return 'Productos';
      case 2: return 'Categorías';
      case 3: return 'Marcas';
      default: return '';
    }
  }

  getNameTypeCampaing(type_campaing: number): string {
    switch (Number(type_campaing)) {
      case 1: return 'Normal';
      case 2: return 'De un día';
      case 3: return 'Por link';
      default: return '';
    }
  }

  irAEditar(id: number): void {
    this.router.navigate(['/discounts/list/edit', id]);
  }

  copyLink(discount: Discount): void {
    const link = `${this.urlTienda}/discount/${discount.code}`;
    navigator.clipboard
      .writeText(link)
      .then(() => this.toastr.success('Enlace copiado al portapapeles', 'Éxito'))
      .catch(() => this.toastr.error('No se pudo copiar el enlace', 'Error'));
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
    if (!this.discountToDelete) return;

    this.isDeleting = true;
    this.discountService.deleteDiscount(this.discountToDelete.id as number).subscribe({
      next: (resp: any) => {
        this.isDeleting = false;
        this.deleteModalVisible = false;

        if (resp?.message == 403) {
          this.toastr.error(resp.message_text ?? 'No se pudo eliminar la campaña', 'Error');
          return;
        }

        this.toastr.success('Campaña eliminada correctamente', 'Éxito');
        this.discountToDelete = null;
        const page = this.discounts.length === 1 && this.currentPage > 1 ? this.currentPage - 1 : this.currentPage;
        this.listDiscounts(page);
      },
      error: (err) => {
        this.isDeleting = false;
        this.deleteModalVisible = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar la campaña', 'Error');
      },
    });
  }
}