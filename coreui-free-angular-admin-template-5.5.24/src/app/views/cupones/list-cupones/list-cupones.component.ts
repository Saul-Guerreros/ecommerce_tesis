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

  // Modal de confirmacion de borrado
  deleteModalVisible = false;
  cuponToDelete: Cupon | null = null;
  isDeleting = false;
  deleteErrorMsg = '';

  constructor(public cuponeService: CuponesService) {}

  ngOnInit(): void {
    this.isLoading$ = this.cuponeService.isLoading$;
    this.listCupones();
  }

  get totalPages(): number {
    return Math.max(1, Math.ceil(this.total / this.pageSize));
  }

  listCupones(page: number = 1): void {
    this.cuponeService.listCupones(page, this.search).subscribe((resp: any) => {
      this.cupones = resp.cupones.data;
      this.total = resp.total;
      this.currentPage = page;
    });
  }

  searchTo(): void {
    this.listCupones(1);
  }

  loadPage(page: number): void {
    if (page < 1 || page > this.totalPages) {
      return;
    }
    this.listCupones(page);
  }

  getNameTypeCupon(type_cupone: number): string {
    switch (type_cupone) {
      case 1:
        return 'Productos';
      case 2:
        return 'Categorias';
      case 3:
        return 'Marcas';
      default:
        return '';
    }
  }

  confirmDelete(cupon: Cupon): void {
    this.cuponToDelete = cupon;
    this.deleteErrorMsg = '';
    this.deleteModalVisible = true;
  }

  cancelDelete(): void {
    this.deleteModalVisible = false;
    this.cuponToDelete = null;
  }

  deleteCupon(): void {
    if (!this.cuponToDelete) {
      return;
    }
    this.isDeleting = true;
    this.deleteErrorMsg = '';
    this.cuponeService.deleteCupone(this.cuponToDelete.id as number).subscribe((resp: any) => {
      this.isDeleting = false;
      if (resp.message == 403) {
        this.deleteErrorMsg = resp.message_text;
        return;
      }
      const index = this.cupones.findIndex((c) => c.id === this.cuponToDelete!.id);
      if (index !== -1) {
        this.cupones.splice(index, 1);
      }
      this.deleteModalVisible = false;
      this.cuponToDelete = null;
    });
  }
}
