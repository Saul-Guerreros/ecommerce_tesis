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
import { Cupon } from '../models/cupon.model';
import { CuponesService } from '../service/cupones.service';

@Component({
  selector: 'app-list-cupones',
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
  templateUrl: './list-cupones.component.html',
})
export class ListCuponesComponent implements OnInit {
  cupones: Cupon[] = [];
  search = '';
  total = 0;
  currentPage = 1;
  pageSize = 25;

  isLoading$: any;

  deleteModalVisible = false;
  cuponToDelete: Cupon | null = null;
  isDeleting = false;

  constructor(
    public cuponeService: CuponesService,
    private router: Router,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.isLoading$ = this.cuponeService.isLoading$;
    this.listCupones();
  }

  get totalPages(): number {
    return Math.max(1, Math.ceil(this.total / this.pageSize));
  }

  get pagesArray(): number[] {
    return Array.from({ length: this.totalPages }, (_, i) => i + 1);
  }

  listCupones(page: number = 1): void {
    this.cuponeService.listCupones(page, this.search).subscribe({
      next: (resp: any) => {
        this.cupones = resp.cupones.data;
        this.total = resp.total;
        this.currentPage = page;
      },
      error: () => this.toastr.error('No se pudieron cargar los cupones', 'Error'),
    });
  }

  searchTo(): void {
    this.listCupones(1);
  }

  loadPage(page: number): void {
    if (page < 1 || page > this.totalPages) return;
    this.listCupones(page);
  }

  getNameTypeCupon(type_cupone: number): string {
    switch (Number(type_cupone)) {
      case 1: return 'Productos';
      case 2: return 'Categorías';
      case 3: return 'Marcas';
      default: return '';
    }
  }

  irAEditar(id: number): void {
    this.router.navigate(['/cupones/list/edit', id]);
  }

  confirmDelete(cupon: Cupon): void {
    this.cuponToDelete = cupon;
    this.deleteModalVisible = true;
  }

  cancelDelete(): void {
    this.deleteModalVisible = false;
    this.cuponToDelete = null;
  }

  deleteCupon(): void {
    if (!this.cuponToDelete) return;

    this.isDeleting = true;
    this.cuponeService.deleteCupone(this.cuponToDelete.id as number).subscribe({
      next: (resp: any) => {
        this.isDeleting = false;

        // Backend heredado: error de negocio con HTTP 200 + message 403
        if (resp?.message == 403) {
          this.toastr.error(resp.message_text ?? 'No se pudo eliminar el cupón', 'Error');
          this.deleteModalVisible = false;
          return;
        }

        this.toastr.success('Cupón eliminado correctamente', 'Éxito');
        this.deleteModalVisible = false;
        this.cuponToDelete = null;

        const page = this.cupones.length === 1 && this.currentPage > 1 ? this.currentPage - 1 : this.currentPage;
        this.listCupones(page);
      },
      error: (err) => {
        this.isDeleting = false;
        this.deleteModalVisible = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar el cupón', 'Error');
      },
    });
  }
}