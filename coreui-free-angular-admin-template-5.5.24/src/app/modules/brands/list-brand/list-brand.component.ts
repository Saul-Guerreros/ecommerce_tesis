import { Component,OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { TableModule, ButtonModule, CardModule } from '@coreui/angular';
import { Marca } from '../models/marca.model';
import { BrandsService } from '../service/brands.service';
import { DeleteBrandComponent } from '../delete-brand/delete-brand.component';

@Component({
  selector: 'app-list-brand',
  standalone: true,
  imports: [CommonModule, TableModule, ButtonModule, CardModule, DeleteBrandComponent],
  templateUrl: './list-brand.component.html',
  styleUrl: './list-brand.component.scss',
})
export class ListBrandComponent {
marcas: Marca[] = [];
  loading = false;

  deleteModalVisible = false;
  marcaSeleccionada: Marca | null = null;

  constructor(
    private brandsService: BrandsService,
    private toastr: ToastrService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.cargarMarcas();
  }

  cargarMarcas(): void {
    this.loading = true;
    this.brandsService.getAll().subscribe({
      next: (res) => {
        this.marcas = res;
        this.loading = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar las marcas', 'Error');
        this.loading = false;
      },
    });
  }

  irACrear(): void {
    this.router.navigate(['/brands/new']);
  }

  irAEditar(id: number): void {
    this.router.navigate(['/brands/editar', id]);
  }

  abrirModalEliminar(marca: Marca): void {
    this.marcaSeleccionada = marca;
    this.deleteModalVisible = true;
  }

  onConfirmarEliminar(): void {
    if (!this.marcaSeleccionada?.mar_id) return;

    this.brandsService.delete(this.marcaSeleccionada.mar_id).subscribe({
      next: () => {
        this.toastr.success('Marca eliminada correctamente', 'Éxito');
        this.deleteModalVisible = false;
        this.cargarMarcas();
      },
      error: (err) => {
        const msg = err?.error?.message || 'No se pudo eliminar la marca';
        this.toastr.error(msg, 'Error');
        this.deleteModalVisible = false;
      },
    });
  }
}
