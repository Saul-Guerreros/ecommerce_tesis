import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime, merge } from 'rxjs';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, FormGroup, ReactiveFormsModule, ValidationErrors, ValidatorFn, Validators } from '@angular/forms';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import { DatosContactoService } from './service/datos-contacto.service';

/** Solo https y del dominio indicado (mismo criterio que UrlRedSocial del backend). */
function urlDe(dominios: string[]): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    const valor = (control.value ?? '').trim();
    if (!valor) return null;
    try {
      const url = new URL(valor);
      const host = url.hostname.toLowerCase().replace(/^(www\.|m\.)/, '');
      return url.protocol === 'https:' && dominios.includes(host) ? null : { dominio: dominios.join(' o ') };
    } catch {
      return { dominio: dominios.join(' o ') };
    }
  };
}

@Component({
  selector: 'app-datos-contacto',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule, SpinnerModule],
  templateUrl: './datos-contacto.component.html',
})
export class DatosContactoComponent implements OnInit {
  form: FormGroup;
  cargando = true;
  guardando = false;
  actualizado: string | null = null;
  mapaPreview: SafeResourceUrl | null = null;
  private destroyRef = inject(DestroyRef);

  constructor(
    private fb: FormBuilder,
    private service: DatosContactoService,
    private toastr: ToastrService,
    private sanitizer: DomSanitizer,
  ) {
    this.form = this.fb.group({
      dco_titulo: ['', [Validators.required, Validators.maxLength(120)]],
      dco_intro: ['', Validators.maxLength(500)],
      dco_direccion: ['', Validators.maxLength(255)],
      dco_referencia: ['', Validators.maxLength(255)],
      dco_telefono: ['', [Validators.maxLength(30), Validators.pattern(/^[0-9+()\s-]{6,30}$/)]],
      dco_email: ['', [Validators.email, Validators.maxLength(120)]],
      dco_whatsapp: ['', Validators.pattern(/^\+?[\d\s-]{10,20}$/)],
      dco_whatsapp_mensaje: ['', Validators.maxLength(200)],
      dco_horario: ['', Validators.maxLength(500)],
      dco_mapa_lat: [null, [Validators.min(-90), Validators.max(90)]],
      dco_mapa_lng: [null, [Validators.min(-180), Validators.max(180)]],
      dco_mapa_zoom: [16, [Validators.required, Validators.min(10), Validators.max(20)]],
      dco_facebook: ['', urlDe(['facebook.com', 'fb.com'])],
      dco_instagram: ['', urlDe(['instagram.com'])],
      dco_youtube: ['', urlDe(['youtube.com', 'youtu.be'])],
      dco_tiktok: ['', urlDe(['tiktok.com'])],
      dco_publicado: [false],
    });
  }

  ngOnInit(): void {
    merge(
      this.form.get('dco_mapa_lat')!.valueChanges,
      this.form.get('dco_mapa_lng')!.valueChanges,
      this.form.get('dco_mapa_zoom')!.valueChanges,
    )
      .pipe(debounceTime(600), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.actualizarMapa());

    this.service.get().subscribe({
      next: (data) => {
        this.form.patchValue(data);
        this.actualizado = data.dco_actualizado ?? null;
        this.actualizarMapa();
        this.form.markAsPristine();
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar los datos de contacto', 'Error');
        this.cargando = false;
      },
    });
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  /** Mensaje del backend o, si no hay, uno local según el error. */
  mensaje(campo: string, porDefecto: string): string {
    const errores = this.form.get(campo)?.errors;
    if (errores?.['backend']) return errores['backend'];
    if (errores?.['dominio']) return `El enlace debe ser https:// de ${errores['dominio']}.`;
    return porDefecto;
  }

  /**
   * Vista previa del mapa. Es seguro marcar la URL como confiable porque
   * se arma SOLO con números validados y un host fijo (nunca con texto libre).
   * Se recalcula solo cuando cambian las coordenadas: si fuera un getter,
   * cada ciclo de detección crearía un objeto nuevo y el iframe se recargaría.
   */
  private actualizarMapa(): void {
    const { dco_mapa_lat, dco_mapa_lng, dco_mapa_zoom } = this.form.getRawValue();
    const lat = Number(dco_mapa_lat);
    const lng = Number(dco_mapa_lng);
    const zoom = Math.min(20, Math.max(10, Number(dco_mapa_zoom) || 16));
    const vacio = dco_mapa_lat === null || dco_mapa_lat === '' || dco_mapa_lng === null || dco_mapa_lng === '';

    if (vacio || !Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
      this.mapaPreview = null;
      return;
    }
    this.mapaPreview = this.sanitizer.bypassSecurityTrustResourceUrl(
      `https://maps.google.com/maps?q=${lat},${lng}&z=${zoom}&output=embed`,
    );
  }

  /** Enlace de prueba para verificar el número antes de publicarlo. */
  get enlaceWhatsapp(): string | null {
    const digitos = String(this.form.value.dco_whatsapp ?? '').replace(/\D/g, '');
    return digitos.length >= 10 ? `https://wa.me/${digitos}` : null;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toastr.warning('Revisá los campos marcados', 'Validación');
      return;
    }

    this.guardando = true;
    this.service.update(this.form.getRawValue()).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Datos actualizados', 'Éxito');
        this.form.patchValue(res.data);
        this.actualizado = res.data.dco_actualizado ?? null;
        this.form.markAsPristine();
        this.guardando = false;
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.entries(err.error.errors as Record<string, string[]>).forEach(([campo, mensajes]) => {
            this.form.get(campo)?.setErrors({ backend: mensajes[0] });
            this.form.get(campo)?.markAsTouched();
          });
          this.toastr.error('Revisá los datos ingresados', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar', 'Error');
      },
    });
  }
}