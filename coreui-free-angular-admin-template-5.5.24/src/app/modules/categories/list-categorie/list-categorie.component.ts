import { Component, OnInit } from '@angular/core';
import { CategoriesService } from '../service/categories.service';
import { Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Categoria } from '../models/categoria.model';
import { TableModule, ButtonModule, CardModule } from '@coreui/angular';
import { CommonModule } from '@angular/common';
import { DeleteCategorieComponent } from '../delete-categorie/delete-categorie.component';

@Component({
  selector: 'app-list-categorie',
   standalone: true,
  imports: [CommonModule, TableModule, ButtonModule, CardModule, DeleteCategorieComponent],
  templateUrl: './list-categorie.component.html',
  styleUrl: './list-categorie.component.scss',
})
export class ListCategorieComponent implements OnInit  {
 categorias: Categoria[] = [];
  loading = false;

  deleteModalVisible = false;
  categoriaSeleccionada: Categoria | null = null;

  constructor(
    private categoriesService: CategoriesService,
    private toastr: ToastrService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.cargarCategorias();
  }

  cargarCategorias(): void {
  this.loading = true;
  this.categoriesService.getAll().subscribe({
    next: (res) => {
      this.categorias = res;   // antes: res.data
      this.loading = false;
    },
    error: () => {
      this.toastr.error('No se pudieron cargar las categorías', 'Error');
      this.loading = false;
    },
  });
}

  irACrear(): void {
    this.router.navigate(['/categories/crear']);
  }

  irAEditar(id: number): void {
    this.router.navigate(['/categories/editar', id]);
  }

  abrirModalEliminar(categoria: Categoria): void {
    this.categoriaSeleccionada = categoria;
    this.deleteModalVisible = true;
  }

  onConfirmarEliminar(): void {
    if (!this.categoriaSeleccionada?.cat_id) return;

    this.categoriesService.delete(this.categoriaSeleccionada.cat_id).subscribe({
      next: () => {
        this.toastr.success('Categoría eliminada correctamente', 'Éxito');
        this.deleteModalVisible = false;
        this.cargarCategorias();
      },
      error: (err) => {
        const msg = err?.error?.message || 'No se pudo eliminar la categoría';
        this.toastr.error(msg, 'Error');
        this.deleteModalVisible = false;
      },
    });
  }
}
