import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { SliderService } from '../services/slider.service';
import { DestinoSliderComponent, validarDestino } from '../destino-slider/destino-slider.component';

@Component({
  selector: 'app-create-slider',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, ButtonModule, CardModule, FormModule, SpinnerModule, DestinoSliderComponent],
  templateUrl: './create-slider.component.html',
})
export class CreateSliderComponent implements OnInit {
  form: FormGroup;
  file: File | null = null;
  preview: string | null = null;
  isLoading$: Observable<boolean>;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    public sliderService: SliderService,
    private router: Router,
    private toastr: ToastrService
  ) {
    this.isLoading$ = this.sliderService.isLoading$;
    this.form = this.fb.group({
      sld_titulo: ['', [Validators.required, Validators.maxLength(150)]],
      sld_subtitulo: [''],
      sld_label: [''],
      sld_destino_tipo: [null],
      sld_destino_id: [null],
      sld_destino_valor: [''],
      sld_orden: [0],
      sld_tipo: [1],
      sld_posicion: [null],
      sld_estado: [1],
      sld_color: ['#000000'],
    });
  }

  ngOnInit(): void {
    this.calcularSiguienteOrden();
    this.form.get('sld_tipo')!.valueChanges.subscribe(() => this.calcularSiguienteOrden());
  }

  private calcularSiguienteOrden(): void {
    this.sliderService.list(1, '').subscribe((resp: any) => {
      const tipoActual = Number(this.form.value.sld_tipo);
      const existentes = (resp.sliders || []).filter((s: any) => Number(s.sld_tipo) === tipoActual);
      const maxOrden = existentes.reduce((max: number, s: any) => Math.max(max, Number(s.sld_orden) || 0), 0);
      this.form.patchValue({ sld_orden: maxOrden + 1 }, { emitEvent: false });
    });
  }

  onFile(event: any): void {
    const f: File = event.target.files[0];
    if (!f) return;
    if (!f.type.startsWith('image/')) {
      this.toastr.warning('El archivo debe ser una imagen', 'Atención');
      return;
    }
    this.file = f;
    const reader = new FileReader();
    reader.onload = () => (this.preview = reader.result as string);
    reader.readAsDataURL(f);
  }

  save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toastr.warning('Completa el título', 'Atención');
      return;
    }
    if (!this.file) {
      this.toastr.warning('Debe subir una imagen', 'Atención');
      return;
    }
    if (Number(this.form.value.sld_tipo) === 2 && !this.form.value.sld_posicion) {
      this.toastr.warning('Elige si el banner secundario va a la izquierda o a la derecha', 'Atención');
      return;
    }
    const errorDestino = validarDestino(this.form.value);
    if (errorDestino) {
      this.toastr.warning(errorDestino, 'Atención');
      return;
    }

    const formData = new FormData();
    Object.entries(this.form.value).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        formData.append(key, String(value));
      }
    });
    formData.append('image', this.file);

    this.guardando = true;
    this.sliderService.create(formData).subscribe({
      next: () => {
        this.toastr.success('Slider creado correctamente', 'Éxito');
        this.router.navigate(['/sliders/list']);
      },
      error: (err) => {
        this.guardando = false;
        this.toastr.error(this.extraerError(err, 'No se pudo crear el slider'), 'Error');
      },
    });
  }

  private extraerError(err: any, porDefecto: string): string {
    const errors = err?.error?.errors;
    if (errors) {
      const primero = Object.values(errors)[0] as string[];
      if (primero?.length) return primero[0];
    }
    return err?.error?.message || porDefecto;
  }
}