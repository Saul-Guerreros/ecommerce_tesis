import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Categoria } from '../models/categoria.model';
import { CategoriesService } from '../service/categories.service';
import { ButtonModule, CardModule, FormModule } from '@coreui/angular';
import { OPCIONES_ICONO } from '../models/iconos-categoria';

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

    opcionesIcono = OPCIONES_ICONO;

    // ---- Imagen de la tarjeta "Comprar por categoría" (solo categorías principales) ----
  imagenActual: string | null = null;
  imagenPreview: string | null = null;
  archivoImagen: File | null = null;
  procesandoImagen = false;

  private readonly formatosPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
  private readonly tamanoMaximo = 2 * 1024 * 1024; // 2 MB, igual que el backend

  seleccionarImagen(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;
    if (!archivo) return;

    if (!this.formatosPermitidos.includes(archivo.type)) {
      this.toastr.warning('Formato no permitido. Usá JPG, PNG o WebP.', 'Imagen');
      input.value = '';
      return;
    }

    if (archivo.size > this.tamanoMaximo) {
      this.toastr.warning('La imagen supera los 2 MB.', 'Imagen');
      input.value = '';
      return;
    }

    this.limpiarSeleccion();
    this.archivoImagen = archivo;
    this.imagenPreview = URL.createObjectURL(archivo); // vista previa local, sin subir
  }

  subirImagen(): void {
    if (!this.archivoImagen) return;

    this.procesandoImagen = true;
    this.categoriesService.subirImagen(this.categoriaId, this.archivoImagen).subscribe({
      next: (res) => {
        this.imagenActual = res.cat_imagen_url ?? null;
        this.limpiarSeleccion();
        this.procesandoImagen = false;
        this.toastr.success('Imagen actualizada', 'Éxito');
      },
      error: (err) => {
        this.procesandoImagen = false;
        this.toastr.error(err?.error?.message || 'No se pudo subir la imagen', 'Error');
      },
    });
  }

  quitarImagen(): void {
    this.procesandoImagen = true;
    this.categoriesService.eliminarImagen(this.categoriaId).subscribe({
      next: () => {
        this.imagenActual = null;
        this.procesandoImagen = false;
        this.toastr.success('Imagen eliminada. Se mostrará el ícono.', 'Éxito');
      },
      error: () => {
        this.procesandoImagen = false;
        this.toastr.error('No se pudo eliminar la imagen', 'Error');
      },
    });
  }

  private limpiarSeleccion(): void {
    if (this.imagenPreview) {
      URL.revokeObjectURL(this.imagenPreview); // libera memoria del navegador
    }
    this.imagenPreview = null;
    this.archivoImagen = null;
  }

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
      this.imagenActual = res.cat_imagen_url ?? null;
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
