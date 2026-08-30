import { CommonModule } from '@angular/common';
import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { IconDirective } from '@coreui/icons-angular';
import {
  ButtonDirective,
  CardBodyComponent,
  CardComponent,
  ColComponent,
  ContainerComponent,
  FormControlDirective,
  FormDirective,
  InputGroupComponent,
  InputGroupTextDirective,
  RowComponent,
} from '@coreui/angular';

import { UsuarioService } from '../../usuarios/services/usuario.service';
import { CargoCatalogo, RolCatalogo, UsuarioFormValue } from '../../usuarios/usuario.model';

const FORM_VACIO: UsuarioFormValue = {
  nombre1: '',
  nombre2: '',
  apellido1: '',
  apellido2: '',
  cedula: '',
  fecha_nacimiento: '',
  cargo_id: null,
  rol_id: null,
  email: '',
  password: '',
};

/**
 * Esta pantalla NO es un auto-registro publico: crea un usuario funcionario
 * (Administrador/Vendedor) igual que el modal "+ Nuevo Usuario" del modulo
 * Usuarios. Por eso la ruta /register esta protegida con authGuard +
 * permissionGuard('usuarios.gestionar') en app.routes.ts - dejarla publica
 * permitiria que cualquiera se cree una cuenta de Administrador.
 */
@Component({
  selector: 'app-register',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ContainerComponent,
    RowComponent,
    ColComponent,
    CardComponent,
    CardBodyComponent,
    FormDirective,
    InputGroupComponent,
    InputGroupTextDirective,
    IconDirective,
    FormControlDirective,
    ButtonDirective,
    RouterLink,
  ],
  templateUrl: './register.component.html',
})
export class RegisterComponent implements OnInit {
  private usuarioService = inject(UsuarioService);
  private router = inject(Router);

  form: UsuarioFormValue = { ...FORM_VACIO };
  confirmPassword = '';

  roles: RolCatalogo[] = [];
  cargos: CargoCatalogo[] = [];

  loading = false;
  errorMsg = '';
  successMsg = '';

  ngOnInit(): void {
    this.usuarioService.catalogos().subscribe((resp) => {
      this.roles = resp.roles;
      this.cargos = resp.cargos;
    });
  }

  register(): void {
    this.errorMsg = '';
    this.successMsg = '';

    if (
      !this.form.nombre1 ||
      !this.form.apellido1 ||
      !this.form.cedula ||
      !this.form.email ||
      !this.form.cargo_id ||
      !this.form.rol_id ||
      !this.form.password
    ) {
      this.errorMsg = 'Completa nombre, apellido, cédula, email, cargo, rol y contraseña (mín. 8 caracteres).';
      return;
    }

    if (this.form.password !== this.confirmPassword) {
      this.errorMsg = 'Las contraseñas no coinciden.';
      return;
    }

    this.loading = true;
    this.usuarioService.create(this.form).subscribe({
      next: () => {
        this.loading = false;
        this.successMsg = 'Usuario creado correctamente.';
        setTimeout(() => this.router.navigateByUrl('/usuarios/list'), 1000);
      },
      error: (err) => {
        this.loading = false;
        this.errorMsg = this.mensajeError(err);
      },
    });
  }

  private mensajeError(err: any): string {
    if (err?.error?.message && typeof err.error.message === 'string') {
      return err.error.message;
    }
    if (err?.error) {
      try {
        const errores = typeof err.error === 'string' ? JSON.parse(err.error) : err.error;
        const primerCampo = Object.keys(errores)[0];
        if (primerCampo && Array.isArray(errores[primerCampo])) {
          return errores[primerCampo][0];
        }
      } catch {
        // usamos el mensaje generico de abajo
      }
    }
    return 'Ocurrió un error. Revisa los datos e intenta nuevamente.';
  }
}
