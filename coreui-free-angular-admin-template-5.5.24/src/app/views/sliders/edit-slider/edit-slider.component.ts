import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { SliderService } from '../services/slider.service';
import { DestinoSliderComponent, validarDestino } from '../destino-slider/destino-slider.component';

@Component({
  selector: 'app-edit-slider',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, ButtonModule, CardModule, FormModule, SpinnerModule, DestinoSliderComponent],
  templateUrl: './edit-slider.component.html',
})
export class EditSliderComponent implements OnInit {
  form: FormGroup;
  file: File | null = null;
  preview: string | null = null;
  isLoading$: Observable<boolean>;
  sliderId!: number;
  guardando = false;
  /** El bloque de destino se crea recién con los datos cargados (incluye el producto elegido). */
  cargado = false;

  constructor(
    private fb: FormBuilder,
    public sliderService: SliderService,
    private route: ActivatedRoute,
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

    this.form.get('sld_tipo')!.valueChanges.subscribe((tipo) => {
      if (Number(tipo) !== 2) {
        this.form.get('sld_posicion')!.setValue(null, { emitEvent: false });
      }
    });
  }

  ngOnInit(): void {
    this.sliderId = Number(this.route.snapshot.params['id']);

    this.sliderService.show(this.sliderId).subscribe({
      next: (resp: any) => {
        const s = resp.slider;
        this.form.patchValue(
          {
            sld_titulo: s.sld_titulo,
            sld_subtitulo: s.sld_subtitulo,
            sld_label: s.sld_label,
            sld_destino_tipo: s.sld_destino_tipo ?? null,
            sld_destino_id: s.sld_destino_id != null ? Number(s.sld_destino_id) : null,
            sld_destino_valor: s.sld_destino_valor ?? '',
            sld_orden: s.sld_orden,
            sld_tipo: s.sld_tipo != null ? Number(s.sld_tipo) : 1,
            sld_posicion: s.sld_posicion != null ? Number(s.sld_posicion) : null,
            sld_estado: s.sld_estado != null ? Number(s.sld_estado) : 1,
            sld_color: s.sld_color || '#000000',
          },
          { emitEvent: false }
        );
        this.preview = s.sld_imagen;
        this.cargado = true;
      },
      error: () => {
        this.toastr.error('No se pudo cargar el slider', 'Error');
        this.router.navigate(['/sliders/list']);
      },
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
    if (this.file) {
      formData.append('image', this.file);
    }

    this.guardando = true;
    this.sliderService.update(this.sliderId, formData).subscribe({
      next: () => {
        this.toastr.success('Slider actualizado correctamente', 'Éxito');
        this.router.navigate(['/sliders/list']);
      },
      error: (err) => {
        this.guardando = false;
        this.toastr.error(this.extraerError(err, 'No se pudo actualizar el slider'), 'Error');
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