import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, FormGroup, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, FormModule, GridModule, SpinnerModule } from '@coreui/angular';
import { AuthService } from '../../auth/services/auth.service';
import { Perfil } from './models/perfil.model';
import { PerfilService } from './service/perfil.service';

/** La confirmación debe coincidir con la nueva contraseña. */
function passwordsIguales(group: AbstractControl): ValidationErrors | null {
  const nueva = group.get('password_nuevo')?.value;
  const conf = group.get('password_nuevo_confirmation')?.value;
  return nueva && conf && nueva !== conf ? { noCoinciden: true } : null;
}

@Component({
  selector: 'app-perfil',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, CardModule, ButtonModule, FormModule, BadgeModule, GridModule, SpinnerModule],
  templateUrl: './perfil.component.html',
})
export class PerfilComponent implements OnInit {
  private fb = inject(FormBuilder);
  private perfilService = inject(PerfilService);
  private authService = inject(AuthService);
  private toastr = inject(ToastrService);

  perfil: Perfil | null = null;
  cargando = true;
  tab: 'datos' | 'seguridad' = 'datos';

  guardandoDatos = false;
  guardandoPassword = false;
  mostrarPasswords = false;

  avatarArchivo: File | null = null;
  avatarPreview: string | null = null;

  readonly hoy = new Date().toISOString().substring(0, 10);

  formDatos: FormGroup = this.fb.group({
    per_nombr1: ['', [Validators.required, Validators.maxLength(45)]],
    per_nombr2: ['', [Validators.maxLength(45)]],
    per_apell1: ['', [Validators.required, Validators.maxLength(50)]],
    per_apell2: ['', [Validators.maxLength(50)]],
    per_fechna: [''],
    celular: ['', [Validators.pattern(/^\+?[0-9\s\-]{6,20}$/)]],
    avatar: [null],
  });

  formPassword: FormGroup = this.fb.group(
    {
      password_actual: ['', [Validators.required]],
      password_nuevo: ['', [Validators.required, Validators.minLength(8)]],
      password_nuevo_confirmation: ['', [Validators.required]],
    },
    { validators: passwordsIguales }
  );

  get iniciales(): string {
    const p = this.perfil?.persona;
    return `${p?.per_nombr1?.[0] ?? ''}${p?.per_apell1?.[0] ?? ''}`.toUpperCase() || '?';
  }

  get fotoActual(): string | null {
    return this.avatarPreview ?? this.perfil?.persona.avatar_url ?? null;
  }

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.perfilService.get().subscribe({
      next: (perfil) => {
        this.aplicarPerfil(perfil);
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar tu perfil', 'Error');
        this.cargando = false;
      },
    });
  }

  public aplicarPerfil(perfil: Perfil): void {
    this.perfil = perfil;
    const p = perfil.persona;
    this.formDatos.reset({
      per_nombr1: p.per_nombr1,
      per_nombr2: p.per_nombr2 ?? '',
      per_apell1: p.per_apell1,
      per_apell2: p.per_apell2 ?? '',
      per_fechna: p.per_fechna ?? '',
      celular: perfil.celular ?? '',
      avatar: null,
    });
    this.avatarArchivo = null;
    this.avatarPreview = null;

    // El header muestra nombre y foto: se sincronizan sin volver a loguear
    this.authService.actualizarUsuario({ full_name: p.nombre_completo, avatar_url: p.avatar_url });
  }

  // ---------- Foto ----------

  onAvatar(event: Event): void {
    const input = event.target as HTMLInputElement;
    const f = input.files?.[0];
    input.value = ''; // permite volver a elegir el mismo archivo
    if (!f) return;

    if (!f.type.startsWith('image/')) {
      this.toastr.warning('El archivo debe ser una imagen', 'Atención');
      return;
    }
    if (f.size > 2 * 1024 * 1024) {
      this.toastr.warning('La imagen no puede superar los 2 MB', 'Atención');
      return;
    }

    this.avatarArchivo = f;
    const reader = new FileReader();
    reader.onload = () => (this.avatarPreview = reader.result as string);
    reader.readAsDataURL(f);
  }

  cancelarAvatar(): void {
    this.avatarArchivo = null;
    this.avatarPreview = null;
  }

  // ---------- Datos personales ----------

  guardarDatos(): void {
    if (this.formDatos.invalid) {
      this.formDatos.markAllAsTouched();
      this.tab = 'datos';
      this.toastr.warning('Revisa los campos marcados', 'Atención');
      return;
    }

    const data = new FormData();
    Object.entries(this.formDatos.value).forEach(([campo, valor]) => {
      if (campo === 'avatar') return;
      if (valor !== null && valor !== undefined && valor !== '') data.append(campo, String(valor));
    });
    if (this.avatarArchivo) data.append('avatar', this.avatarArchivo);

    this.guardandoDatos = true;
    this.perfilService.update(data).subscribe({
      next: (res) => {
        this.guardandoDatos = false;
        this.aplicarPerfil(res.data);
        this.toastr.success(res.message, 'Éxito');
      },
      error: (err) => {
        this.guardandoDatos = false;
        this.aplicarErrores(this.formDatos, err, 'No se pudo actualizar el perfil');
      },
    });
  }

  // ---------- Contraseña ----------

  guardarPassword(): void {
    if (this.formPassword.invalid) {
      this.formPassword.markAllAsTouched();
      return;
    }

    this.guardandoPassword = true;
    this.perfilService.cambiarPassword(this.formPassword.value).subscribe({
      next: (res) => {
        this.guardandoPassword = false;
        this.formPassword.reset();
        this.mostrarPasswords = false;
        this.toastr.success(res.message, 'Éxito');
      },
      error: (err) => {
        this.guardandoPassword = false;
        this.aplicarErrores(this.formPassword, err, 'No se pudo cambiar la contraseña');
      },
    });
  }

  // ---------- Helpers de validación ----------

  campoInvalido(form: FormGroup, campo: string): boolean {
    const c = form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(form: FormGroup, campo: string): string | null {
    return form.get(campo)?.errors?.['backend'] ?? null;
  }

  private aplicarErrores(form: FormGroup, err: any, porDefecto: string): void {
    const errors = err?.error?.errors;
    if (err?.status === 422 && errors) {
      Object.keys(errors).forEach((campo) => {
        const ctrl = form.get(campo);
        ctrl?.setErrors({ backend: errors[campo][0] });
        ctrl?.markAsTouched();
      });
      // El error de avatar no tiene input visible en el form: se muestra en toast
      this.toastr.error(errors['avatar']?.[0] ?? 'Revisa los campos marcados', 'Validación');
      return;
    }
    this.toastr.error(err?.error?.message ?? porDefecto, 'Error');
  }
}