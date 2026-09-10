import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Usuario, RolCatalogo, CargoCatalogo, SucursalCatalogo } from '../models/usuario.model';
import { UsuariosService } from '../service/usuarios.service';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-form-usuario',
  standalone: true,
imports: [
  CommonModule,
  ReactiveFormsModule,
  FormsModule,
  ButtonModule,
  FormModule,
  ModalModule,
  SpinnerModule
],  templateUrl: './form-usuario.component.html'
  
})
export class FormUsuarioComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() usuario: Usuario | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  roles: RolCatalogo[] = [];
  cargos: CargoCatalogo[] = [];
  sucursales: SucursalCatalogo[] = [];

  sucursalesSeleccionadas = new Set<number>();
  sucursalPrincipal: number | null = null;

  constructor(private fb: FormBuilder, private service: UsuariosService, private toastr: ToastrService) {
    this.form = this.fb.group({
      nombre1: ['', [Validators.required, Validators.maxLength(45)]],
      nombre2: ['', [Validators.maxLength(45)]],
      apellido1: ['', [Validators.required, Validators.maxLength(50)]],
      apellido2: ['', [Validators.maxLength(50)]],
      cedula: ['', [Validators.required, Validators.maxLength(50)]],
      fecha_nacimiento: [''],
      cargo_id: [null, [Validators.required]],
      rol_id: [null, [Validators.required]],
      email: ['', [Validators.required, Validators.email]],
      password: [''],
    });
  }

  ngOnInit(): void {
    this.service.catalogos().subscribe({
      next: (res) => { this.roles = res.roles; this.cargos = res.cargos; this.sucursales = res.sucursales; },
      error: () => this.toastr.error('No se pudieron cargar roles, cargos y sucursales', 'Error'),
    });
  }

  get esEdicion(): boolean {
    return !!this.usuario?.usuario_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      if (this.usuario) {
        this.form.reset({
          nombre1: this.usuario.persona.nombre1,
          nombre2: this.usuario.persona.nombre2 ?? '',
          apellido1: this.usuario.persona.apellido1,
          apellido2: this.usuario.persona.apellido2 ?? '',
          cedula: this.usuario.persona.cedula,
          fecha_nacimiento: this.usuario.persona.fecha_nacimiento ?? '',
          cargo_id: this.usuario.cargo_id,
          rol_id: this.usuario.rol_id,
          email: this.usuario.email,
          password: '',
        });

        this.sucursalesSeleccionadas = new Set(this.usuario.sucursales.map((s) => s.suc_id));
        this.sucursalPrincipal = this.usuario.sucursales.find((s) => s.principal)?.suc_id ?? null;
      } else {
        this.form.reset();
        this.sucursalesSeleccionadas = new Set();
        this.sucursalPrincipal = null;
      }

      this.guardando = false;
    }
  }

  alternarSucursal(sucId: number, event: Event): void {
    const marcado = (event.target as HTMLInputElement).checked;

    if (marcado) {
      this.sucursalesSeleccionadas.add(sucId);
      if (this.sucursalesSeleccionadas.size === 1) {
        this.sucursalPrincipal = sucId;
      }
    } else {
      this.sucursalesSeleccionadas.delete(sucId);
      if (this.sucursalPrincipal === sucId) {
        this.sucursalPrincipal = null;
      }
    }
  }

  get mostrarSelectorPrincipal(): boolean {
    return this.sucursalesSeleccionadas.size > 1;
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    if (!this.esEdicion) {
      const pass = this.form.value.password;
      if (!pass || pass.length < 8) {
        this.form.get('password')?.setErrors({ backend: 'La contraseña es obligatoria (mínimo 8 caracteres).' });
        this.form.get('password')?.markAsTouched();
        return;
      }
    }

    if (this.sucursalesSeleccionadas.size === 0) {
      this.toastr.error('Debe asignar al menos una sucursal', 'Falta dato');
      return;
    }

    if (this.mostrarSelectorPrincipal && this.sucursalPrincipal === null) {
      this.toastr.error('Debe indicar cuál sucursal es la principal', 'Falta dato');
      return;
    }

    this.guardando = true;

    const payload = {
      ...this.form.value,
      sucursales: Array.from(this.sucursalesSeleccionadas),
      sucursal_principal: this.sucursalPrincipal,
    };

    if (this.esEdicion && !payload.password) {
      delete payload.password;
    }

    const peticion = this.esEdicion
      ? this.service.update(this.usuario!.usuario_id, payload)
      : this.service.create(payload);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Operación realizada', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            const base = campo.split('.')[0];
            const control = this.form.get(base);
            if (control) {
              control.setErrors({ backend: err.error.errors[campo][0] });
              control.markAsTouched();
            } else {
              this.toastr.error(err.error.errors[campo][0], 'Validación');
            }
          });
          if (this.form.get('email')?.errors || this.form.get('cedula')?.errors) {
            this.toastr.error('Revise los datos ingresados', 'Validación');
          }
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el usuario', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}