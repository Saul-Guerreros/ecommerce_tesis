import { CommonModule } from '@angular/common';
import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';
import {
  BadgeModule,
  ButtonModule,
  CardModule,
  DropdownComponent,
  DropdownItemDirective,
  DropdownMenuDirective,
  DropdownToggleDirective,
  FormModule,
  ModalModule,
  PaginationModule,
  SpinnerModule,
  TableModule,
} from '@coreui/angular';

import { UsuarioService } from '../services/usuario.service';
import { AuthService } from '../../../auth/services/auth.service';
import {
  CargoCatalogo,
  EstadoUsuario,
  RolCatalogo,
  Usuario,
  UsuarioFormValue,
} from '../usuario.model';

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

@Component({
  selector: 'app-list-usuario',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ButtonModule,
    CardModule,
    TableModule,
    ModalModule,
    FormModule,
    BadgeModule,
    SpinnerModule,
    PaginationModule,
    DropdownComponent,
    DropdownToggleDirective,
    DropdownMenuDirective,
    DropdownItemDirective,
  ],
  templateUrl: './list-usuario.component.html',
})
export class ListUsuarioComponent implements OnInit {
  private authService = inject(AuthService);

  // ---- Listado ----
  usuarios: Usuario[] = [];
  search = '';
  filtroEstado = '';
  filtroRolId: number | null = null;
  total = 0;
  currentPage = 1;
  lastPage = 1;

  roles: RolCatalogo[] = [];
  cargos: CargoCatalogo[] = [];

  isLoading$: Observable<boolean>;

  constructor(public usuarioService: UsuarioService) {
    this.isLoading$ = this.usuarioService.isLoading$;
  }

  ngOnInit(): void {
    this.cargarCatalogos();
    this.loadList();
  }

  cargarCatalogos(): void {
    this.usuarioService.catalogos().subscribe((resp) => {
      this.roles = resp.roles;
      this.cargos = resp.cargos;
    });
  }

  loadList(page: number = 1): void {
    this.usuarioService
      .list(page, this.search, this.filtroEstado, this.filtroRolId)
      .subscribe((resp) => {
        this.usuarios = resp.data;
        this.total = resp.meta.total;
        this.currentPage = resp.meta.current_page;
        this.lastPage = resp.meta.last_page;
      });
  }

  get pagesArray(): number[] {
    return Array.from({ length: this.lastPage }, (_, i) => i + 1);
  }

  searchTo(): void {
    this.loadList(1);
  }

  changePage(page: number): void {
    if (page < 1 || page > this.lastPage) return;
    this.loadList(page);
  }

  esMiPropioUsuario(usuario: Usuario): boolean {
    return this.authService.currentUser()?.email === usuario.email;
  }

  colorEstado(estado: EstadoUsuario): string {
    switch (estado) {
      case 'ACTIVO':
        return 'success';
      case 'INACTIVO':
        return 'secondary';
      case 'BLOQUEADO':
        return 'danger';
      default:
        return 'secondary';
    }
  }

  // ---- Crear ----
  showCreate = false;
  createForm: UsuarioFormValue = { ...FORM_VACIO };
  createError = '';

  openCreate(): void {
    this.createForm = { ...FORM_VACIO };
    this.createError = '';
    this.showCreate = true;
  }

  guardarCreate(): void {
    if (!this.formularioValido(this.createForm, true)) {
      this.createError = 'Completa nombre, apellido, cédula, email, cargo, rol y contraseña (mín. 8 caracteres).';
      return;
    }
    this.usuarioService.create(this.createForm).subscribe({
      next: () => {
        this.showCreate = false;
        this.loadList(1);
      },
      error: (err) => {
        this.createError = this.mensajeError(err);
      },
    });
  }

  // ---- Editar ----
  showEdit = false;
  editUsuario: Usuario | null = null;
  editForm: UsuarioFormValue = { ...FORM_VACIO };
  editError = '';

  openEdit(usuario: Usuario): void {
    this.editUsuario = usuario;
    this.editForm = {
      nombre1: usuario.persona.nombre1,
      nombre2: usuario.persona.nombre2 ?? '',
      apellido1: usuario.persona.apellido1,
      apellido2: usuario.persona.apellido2 ?? '',
      cedula: usuario.persona.cedula,
      fecha_nacimiento: usuario.persona.fecha_nacimiento ?? '',
      cargo_id: usuario.cargo_id,
      rol_id: usuario.rol_id,
      email: usuario.email,
      password: '', // vacío = no se cambia
    };
    this.editError = '';
    this.showEdit = true;
  }

  guardarEdit(): void {
    if (!this.editUsuario) return;
    if (!this.formularioValido(this.editForm, false)) {
      this.editError = 'Completa nombre, apellido, cédula, email, cargo y rol.';
      return;
    }
    this.usuarioService.update(this.editUsuario.usuario_id, this.editForm).subscribe({
      next: (resp) => {
        const idx = this.usuarios.findIndex((u) => u.usuario_id === resp.data.usuario_id);
        if (idx !== -1) this.usuarios[idx] = resp.data;
        this.showEdit = false;
      },
      error: (err) => {
        this.editError = this.mensajeError(err);
      },
    });
  }

  // ---- Cambio de estado (activar / desactivar / desbloquear) ----
  cambiarEstado(usuario: Usuario, estado: EstadoUsuario): void {
    if (this.esMiPropioUsuario(usuario) && estado !== 'ACTIVO') {
      return; // el backend también lo rechaza; esto evita el click innecesario
    }
    this.usuarioService.cambiarEstado(usuario.usuario_id, estado).subscribe((resp) => {
      const idx = this.usuarios.findIndex((u) => u.usuario_id === resp.data.usuario_id);
      if (idx !== -1) this.usuarios[idx] = resp.data;
    });
  }

  // ---- Helpers ----
  private formularioValido(form: UsuarioFormValue, esCreacion: boolean): boolean {
    const base =
      !!form.nombre1 &&
      !!form.apellido1 &&
      !!form.cedula &&
      !!form.email &&
      !!form.cargo_id &&
      !!form.rol_id;

    if (!esCreacion) return base;

    return base && !!form.password && form.password.length >= 8;
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
        // ignorar, usamos el mensaje genérico de abajo
      }
    }
    return 'Ocurrió un error. Revisa los datos e intenta nuevamente.';
  }
}
