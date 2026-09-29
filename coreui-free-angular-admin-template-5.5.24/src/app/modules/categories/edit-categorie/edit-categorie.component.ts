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

  opcionesIcono = [
    { valor: 'desktop', etiqueta: 'Computadora de escritorio' },
    { valor: 'tablet', etiqueta: 'Tablet / Notebook' },
    { valor: 'server', etiqueta: 'Servidor / Componentes' },
    { valor: 'world', etiqueta: 'Redes' },
    { valor: 'print', etiqueta: 'Impresión' },
    { valor: 'database', etiqueta: 'Almacenamiento' },
    { valor: 'cog', etiqueta: 'Configuración / Repuestos' },
    { valor: 'bag', etiqueta: 'Accesorios' },
    { valor: 'gift', etiqueta: 'Promociones' },
    { valor: 'grid', etiqueta: 'General' },
  ];

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
      cat_icono: [null],
    });

    // si el admin elige una categoría padre acá, deja de ser "raíz" -> sin ícono
    this.form.get('cat_idpadr')?.valueChanges.subscribe((valor) => {
      if (valor) {
        this.form.get('cat_icono')?.setValue(null);
      }
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
        cat_nombre: res.cat_nombre,
        cat_idpadr: res.cat_idpadr,
        cat_icono: res.cat_icono,
      }, { emitEvent: false }); 
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
