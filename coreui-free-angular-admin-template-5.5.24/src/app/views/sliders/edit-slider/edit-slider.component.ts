import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';
import { ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { SliderService } from '../services/slider.service';

@Component({
  selector: 'app-edit-slider',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterLink,
    ButtonModule,
    CardModule,
    FormModule,
    SpinnerModule,
  ],
  templateUrl: './edit-slider.component.html',
})
export class EditSliderComponent implements OnInit {
  form: FormGroup;
  file: File | null = null;
  preview: string | null = null;
  isLoading$: Observable<boolean>;
  errorMsg = '';
  successMsg = '';
  sliderId!: number;

  productos: any[] = [];
  productoSearch = '';

  constructor(
    private fb: FormBuilder,
    public sliderService: SliderService,
    private route: ActivatedRoute,
    private router: Router
  ) {
    this.isLoading$ = this.sliderService.isLoading$;
    this.form = this.fb.group({
      sld_prdid: [null, [Validators.required]],
      sld_titulo: ['', [Validators.required, Validators.maxLength(150)]],
      sld_subtitulo: [''],
      sld_label: [''],
      sld_enlace: [''],
      sld_orden: [0],
      sld_tipo: [1],
      sld_posicion: [null],
      sld_estado: [1],
      sld_color: ['#000000'],
    });
  }

  ngOnInit(): void {
    this.sliderId = Number(this.route.snapshot.params['id']);
    this.loadProductos();

    this.sliderService.show(this.sliderId).subscribe((resp: any) => {
      const s = resp.slider;
      this.form.patchValue({
        sld_prdid: s.sld_prdid,
        sld_titulo: s.sld_titulo,
        sld_subtitulo: s.sld_subtitulo,
        sld_label: s.sld_label,
        sld_enlace: s.sld_enlace,
        sld_orden: s.sld_orden,
        sld_tipo: s.sld_tipo,
        sld_posicion: s.sld_posicion,
        sld_estado: s.sld_estado,
        sld_color: s.sld_color || '#000000',
      });
      this.preview = s.sld_imagen;

      // Asegura que el producto actualmente asignado aparezca en el <select>
      // aunque no esté dentro de los primeros resultados de la búsqueda.
      this.sliderService.showProducto(s.sld_prdid).subscribe((r: any) => {
        const yaExiste = this.productos.some((p) => p.prd_id === r.producto.prd_id);
        if (!yaExiste) {
          this.productos = [r.producto, ...this.productos];
        }
      });
    });
  }

  loadProductos(): void {
    this.sliderService.listProductos(this.productoSearch).subscribe((resp: any) => {
      this.productos = resp.productos;
    });
  }

  onFile(event: any): void {
    const f: File = event.target.files[0];
    if (!f) return;
    if (!f.type.startsWith('image/')) {
      this.errorMsg = 'El archivo debe ser una imagen';
      return;
    }
    this.errorMsg = '';
    this.file = f;
    const reader = new FileReader();
    reader.onload = () => (this.preview = reader.result as string);
    reader.readAsDataURL(f);
  }

  save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.errorMsg = 'Completa los campos obligatorios (*)';
      return;
    }

    if (Number(this.form.value.sld_tipo) === 2 && !this.form.value.sld_posicion) {
      this.errorMsg = 'Elige si el banner secundario va a la izquierda o a la derecha';
      return;
    }

    const formData = new FormData();
    Object.entries(this.form.value).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        formData.append(key, value as string);
      }
    });
    if (this.file) {
      formData.append('image', this.file);
    }

    this.sliderService.update(this.sliderId, formData).subscribe({
      next: () => {
        this.successMsg = 'El slider se actualizó correctamente';
        this.errorMsg = '';
      },
      error: (err) => (this.errorMsg = err?.error?.message || 'Ocurrió un error al actualizar'),
    });
  }
}
