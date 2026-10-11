import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';
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
import { IconModule } from '@coreui/icons-angular';
import { SliderService } from '../services/slider.service';

@Component({
  selector: 'app-list-slider',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterLink,
    ButtonModule,
    CardModule,
    TableModule,
    ModalModule,
    FormModule,
    BadgeModule,
    SpinnerModule,
    PaginationModule,
    IconModule,
  ],
  templateUrl: './list-slider.component.html',
})
export class ListSliderComponent implements OnInit {
  sliders: any[] = [];
  search = '';
  total = 0;
  currentPage = 1;
  pageSize = 25;

  deleteModalVisible = false;
  sliderToDelete: any = null;

  isLoading$: Observable<boolean>;

  constructor(
    public sliderService: SliderService,
    private toastr: ToastrService,
    private router: Router
  ) {
    this.isLoading$ = this.sliderService.isLoading$;
  }

  ngOnInit(): void {
    this.loadList();
  }

  loadList(page: number = 1): void {
    this.sliderService.list(page, this.search).subscribe({
      next: (resp: any) => {
        this.sliders = resp.sliders;
        this.total = resp.total;
        this.currentPage = page;
      },
      error: () => this.toastr.error('No se pudieron cargar los sliders', 'Error'),
    });
  }

  get totalPages(): number {
    return Math.ceil(this.total / this.pageSize) || 1;
  }

  get pagesArray(): number[] {
    return Array.from({ length: this.totalPages }, (_, i) => i + 1);
  }

  searchTo(): void {
    this.loadList(1);
  }

  tipoLabel(tipo: number): string {
    switch (Number(tipo)) {
      case 1: return 'Principal';
      case 2: return 'Secundario';
      case 3: return 'Terciario';
      default: return '—';
    }
  }

  changePage(page: number): void {
    if (page < 1 || page > this.totalPages) return;
    this.loadList(page);
  }

  irAEditar(id: number): void {
    this.router.navigate(['/sliders/editar', id]);
  }

  confirmDelete(slider: any): void {
    this.sliderToDelete = slider;
    this.deleteModalVisible = true;
  }

  deleteSlider(): void {
    if (!this.sliderToDelete?.sld_id) return;

    this.sliderService.delete(this.sliderToDelete.sld_id).subscribe({
      next: () => {
        this.toastr.success('Slider eliminado correctamente', 'Éxito');
        this.deleteModalVisible = false;
        this.sliderToDelete = null;
        this.loadList(this.currentPage);
      },
      error: (err) => {
        this.toastr.error(err?.error?.message || 'No se pudo eliminar el slider', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }
}