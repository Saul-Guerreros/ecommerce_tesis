import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormBuilder, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';
import { ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { SliderService } from '../services/slider.service';

@Component({
  selector: 'app-create-slider',
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
  templateUrl: './create-slider.component.html',
})
export class CreateSliderComponent {
  form: FormGroup;
  file: File | null = null;
  preview: string | null = null;
  isLoading$: Observable<boolean>;
  errorMsg = '';

  productos: any[] = [];
  productoSearch = '';

  constructor(
    private fb: FormBuilder,
    public sliderService: SliderService,
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
	  this.loadProductos();
	  this.calcularSiguienteOrden();
	  this.form.get('sld_tipo')!.valueChanges.subscribe(() => this.calcularSiguienteOrden());
	}
	
	private calcularSiguienteOrden(): void {
  this.sliderService.list(1, '').subscribe((resp: any) => {
    const tipoActual = Number(this.form.value.sld_tipo);
    const existentes = (resp.sliders || []).filter(
      (s: any) => Number(s.sld_tipo) === tipoActual
    );
    const maxOrden = existentes.reduce(
      (max: number, s: any) => Math.max(max, Number(s.sld_orden) || 0),
      0
    );
    this.form.patchValue({ sld_orden: maxOrden + 1 });
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
    if (this.form.invalid || !this.file) {
      this.form.markAllAsTouched();
      this.errorMsg = 'Completa los campos obligatorios (*) y sube una imagen';
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
    formData.append('image', this.file);

    this.sliderService.create(formData).subscribe({
      next: () => this.router.navigate(['/sliders/list']),
      error: (err) => (this.errorMsg = err?.error?.message || 'Ocurrió un error al guardar'),
    });
  }
}
