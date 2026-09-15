import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import {
  BadgeModule, ButtonModule, CardModule, DropdownItemDirective,
  DropdownMenuDirective, DropdownToggleDirective, FormModule, PaginationModule,
  SpinnerModule, TableModule,
} from '@coreui/angular';
import { Usuario, RolCatalogo, EstadoUsuario } from '../models/usuario.model';
import { UsuariosService } from '../service/usuarios.service';
import { FormUsuarioComponent } from '../form-usuario/form-usuario.component';
import { AuthService } from '../../../auth/services/auth.service';

@Component({
  selector: 'app-list-usuario',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule,
    SpinnerModule, FormModule, PaginationModule,
   DropdownToggleDirective, DropdownMenuDirective, DropdownItemDirective,
    FormUsuarioComponent,
  ],
  templateUrl: './list-usuario.component.html'
})
export class ListUsuarioComponent implements OnInit {
  usuarios: Usuario[] = [];
  roles: RolCatalogo[] = [];
  cargando = false;

  search = '';
  filtroEstado = '';
  filtroRolId: number | null = null;

  currentPage = 1;
  lastPage = 1;
  total = 0;

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  usuarioSeleccionado: Usuario | null = null;

  constructor(
    private service: UsuariosService,
    private toastr: ToastrService,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    this.cargar();

    this.service.catalogos().subscribe({
      next: (res) => (this.roles = res.roles),
      error: () => {},
    });

    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar(1));
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(page: number = this.currentPage): void {
    this.cargando = true;
    this.service.list(page, this.search, this.filtroEstado, this.filtroRolId).subscribe({
      next: (res) => {
        this.usuarios = res.data;
        this.currentPage = res.meta.current_page;
        this.lastPage = res.meta.last_page;
        this.total = res.meta.total;
        this.cargando = false;
      },
      error: () => { this.toastr.error('No se pudieron cargar los usuarios', 'Error'); this.cargando = false; },
    });
  }

  get pagesArray(): number[] {
    return Array.from({ length: this.lastPage }, (_, i) => i + 1);
  }

  cambiarPagina(page: number): void {
    if (page < 1 || page > this.lastPage) return;
    this.cargar(page);
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.filtroRolId = null;
    this.cargar(1);
  }

  abrirNuevo(): void {
    this.usuarioSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(u: Usuario): void {
    this.usuarioSeleccionado = { ...u };
    this.formModalVisible = true;
  }

  esMiPropioUsuario(u: Usuario): boolean {
    return this.authService.currentUser()?.email === u.email;
  }

  colorEstado(estado: EstadoUsuario): string {
    switch (estado) {
      case 'ACTIVO': return 'success';
      case 'BLOQUEADO': return 'danger';
      default: return 'secondary';
    }
  }

  cambiarEstado(u: Usuario, estado: EstadoUsuario): void {
    if (this.esMiPropioUsuario(u) && estado !== 'ACTIVO') {
      this.toastr.error('No puedes desactivar o bloquear tu propia cuenta', 'Acción no permitida');
      return;
    }

    this.service.cambiarEstado(u.usuario_id, estado).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}