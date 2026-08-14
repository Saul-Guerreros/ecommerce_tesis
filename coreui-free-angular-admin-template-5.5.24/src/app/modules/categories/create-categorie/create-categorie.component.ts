import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Categoria } from '../models/categoria.model';
import { CategoriesService } from '../service/categories.service';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ButtonModule, CardModule, FormModule } from '@coreui/angular';

@Component({
  selector: 'app-create-categorie',
   standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule],
  templateUrl: './create-categorie.component.html',
  styleUrl: './create-categorie.component.scss',
})
export class CreateCategorieComponent implements OnInit{
form: FormGroup;
  categoriasPadre: Categoria[] = [];
  esSubcategoria = false;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private categoriesService: CategoriesService,
    private toastr: ToastrService,
    private router: Router
  ) {
    this.form = this.fb.group({
      cat_nombre: ['', [Validators.required, Validators.maxLength(45)]],
      cat_idpadr: [null],
    });
  }

  ngOnInit(): void {
    this.cargarCategoriasPadre();
  }

 cargarCategoriasPadre(): void {
  this.categoriesService.getAll().subscribe({
    next: (res) => (this.categoriasPadre = res),   // antes: res.data
    error: () => this.toastr.error('No se pudieron cargar las categorías padre', 'Error'),
  });
}

  toggleSubcategoria(): void {
    this.esSubcategoria = !this.esSubcategoria;
    if (!this.esSubcategoria) {
      this.form.get('cat_idpadr')?.setValue(null);
    }
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.categoriesService.create(this.form.value).subscribe({
      next: () => {
        this.toastr.success('Categoría creada correctamente', 'Éxito');
        this.router.navigate(['/categories']);
      },
      error: (err) => {
        const msg = err?.error?.message || 'No se pudo crear la categoría';
        this.toastr.error(msg, 'Error');
        this.guardando = false;
      },
    });
  }

  cancelar(): void {
    this.router.navigate(['/categories']);
  }
}
