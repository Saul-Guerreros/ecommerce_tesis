import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Categoria } from '../models/categoria.model';
import { CategoriesService } from '../service/categories.service';
import { ButtonModule, CardModule, FormModule } from '@coreui/angular';

@Component({
  selector: 'app-edit-categorie',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule],
  templateUrl: './edit-categorie.component.html',
  styleUrl: './edit-categorie.component.scss',
})
export class EditCategorieComponent {
 form: FormGroup;
  categoriasPadre: Categoria[] = [];
  categoriaId!: number;
  guardando = false;
  cargando = true;

  constructor(
    private fb: FormBuilder,
    private categoriesService: CategoriesService,
    private toastr: ToastrService,
    private route: ActivatedRoute,
    private router: Router
  ) {
    this.form = this.fb.group({
      cat_nombre: ['', [Validators.required, Validators.maxLength(45)]],
      cat_idpadr: [null],
    });
  }

  ngOnInit(): void {
    this.categoriaId = Number(this.route.snapshot.paramMap.get('id'));
    this.cargarCategoriasPadre();
    this.cargarCategoria();
  }

 cargarCategoriasPadre(): void {
  this.categoriesService.getAll().subscribe({
    next: (res) => {
      this.categoriasPadre = res.filter(c => c.cat_id !== this.categoriaId);   // antes: res.data.filter(...)
    },
  });
}

cargarCategoria(): void {
  this.categoriesService.getById(this.categoriaId).subscribe({
    next: (res) => {
      this.form.patchValue({
        cat_nombre: res.cat_nombre,   // antes: res.data.cat_nombre
        cat_idpadr: res.cat_idpadr,   // antes: res.data.cat_idpadr
      });
      this.cargando = false;
    },
    error: () => {
      this.toastr.error('No se pudo cargar la categoría', 'Error');
      this.router.navigate(['/categories']);
    },
  });
}

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.categoriesService.update(this.categoriaId, this.form.value).subscribe({
      next: () => {
        this.toastr.success('Categoría actualizada correctamente', 'Éxito');
        this.router.navigate(['/categories']);
      },
      error: (err) => {
        const msg = err?.error?.message || 'No se pudo actualizar la categoría';
        this.toastr.error(msg, 'Error');
        this.guardando = false;
      },
    });
  }

  cancelar(): void {
    this.router.navigate(['/categories']);
  }
}
