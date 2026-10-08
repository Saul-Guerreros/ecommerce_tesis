import { CommonModule } from '@angular/common';
import { Component, effect } from '@angular/core';
import { AuthService } from '../../auth/service/auth.service';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import {
  CiudadCatalogo,
  DepartamentoCatalogo,
  Direccion,
} from '../../../core/models/direccion.model';
import { DireccionClienteService } from '../../../core/services/direccion-cliente.service';

const ALIAS_SUGERIDOS = ['Mi Casa', 'Mi Trabajo', 'Otro'];

@Component({
  selector: 'app-direcciones',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './direcciones.component.html',
  styleUrl: './direcciones.component.css',
})
export class DireccionesComponent {
  cargando = true;
  guardando = false;

  direcciones: Direccion[] = [];

  departamentos: DepartamentoCatalogo[] = [];
  private ciudades: CiudadCatalogo[] = [];
  ciudadesFiltradas: CiudadCatalogo[] = [];

  mostrarFormulario = false;
  direccionEnEdicion: Direccion | null = null;

  alias = ALIAS_SUGERIDOS;
  aliasOpcion = 'Mi Casa';
  dir_alias = 'Mi Casa';

  departamentoId: number | null = null;
  dir_ciuid: number | null = null;
  dir_barrio = '';
  dir_calle = '';
  dir_nrocasa = '';
  dir_referencia = '';
  dir_celular = '';
  dir_telef = '';
  dir_predet = false;

  constructor(
    private direccionService: DireccionClienteService,
    private toastr: ToastrService,
    private authService: AuthService,
  ) {
    effect(() => {
      if (this.authService.sesion()) {
        this.cargarTodo();
      }
    });
  }

  private cargarTodo(): void {
    this.cargando = true;
    this.direccionService.catalogos().subscribe({
      next: (catalogos) => {
        this.departamentos = catalogos.departamentos;
        this.ciudades = catalogos.ciudades;
        this.cargarDirecciones();
      },
      error: () => (this.cargando = false),
    });
  }

  private cargarDirecciones(): void {
    this.direccionService.listar().subscribe({
      next: (resp) => {
        this.direcciones = resp.data;
        this.cargando = false;
      },
      error: () => (this.cargando = false),
    });
  }

  // ----- Cascada Departamento -> Ciudad (barrio y calle son texto libre) -----

  get aliasEsOtro(): boolean {
    return this.aliasOpcion === 'Otro';
  }

  cambiarAlias(opcion: string): void {
    this.aliasOpcion = opcion;
    this.dir_alias = opcion === 'Otro' ? '' : opcion;
  }

  onDepartamentoChange(): void {
    this.dir_ciuid = null;
    this.ciudadesFiltradas = this.departamentoId
      ? this.ciudades.filter((c) => c.ciu_depid === this.departamentoId)
      : [];
  }

  abrirNuevo(): void {
    this.direccionEnEdicion = null;
    this.aliasOpcion = 'Mi Casa';
    this.dir_alias = 'Mi Casa';
    this.departamentoId = null;
    this.dir_ciuid = null;
    this.dir_barrio = '';
    this.dir_calle = '';
    this.dir_nrocasa = '';
    this.dir_referencia = '';
    this.dir_celular = '';
    this.dir_telef = '';
    this.dir_predet = this.direcciones.length === 0;
    this.ciudadesFiltradas = [];
    this.mostrarFormulario = true;
  }

  abrirEditar(direccion: Direccion): void {
    this.direccionEnEdicion = direccion;

    // El listado solo trae el nombre de la ciudad y del departamento, no
    // sus ids; se ubican en el catalogo ya cargado para preseleccionar.
    const ciudad = this.ciudades.find((c) => c.ciu_id === direccion.dir_ciuid);
    this.departamentoId = ciudad?.ciu_depid ?? null;
    this.ciudadesFiltradas = this.departamentoId
      ? this.ciudades.filter((c) => c.ciu_depid === this.departamentoId)
      : [];

    this.dir_ciuid = direccion.dir_ciuid;

    this.aliasOpcion = this.alias.includes(direccion.dir_alias) ? direccion.dir_alias : 'Otro';
    this.dir_alias = direccion.dir_alias;

    this.dir_barrio = direccion.dir_barrio;
    this.dir_calle = direccion.dir_calle;
    this.dir_nrocasa = direccion.dir_nrocasa ?? '';
    this.dir_referencia = direccion.dir_referencia ?? '';
    this.dir_celular = direccion.dir_celular ?? '';
    this.dir_telef = direccion.dir_telef ?? '';
    this.dir_predet = direccion.dir_predet;

    this.mostrarFormulario = true;
  }

  cerrarFormulario(): void {
    this.mostrarFormulario = false;
    this.direccionEnEdicion = null;
  }

  guardar(): void {
    if (!this.dir_ciuid || !this.dir_barrio || !this.dir_calle) {
      this.toastr.warning('Seleccioná la ciudad y completá barrio y calle.', 'Faltan datos');
      return;
    }

    const datos = {
      dir_alias: this.dir_alias,
      dir_ciuid: this.dir_ciuid,
      dir_barrio: this.dir_barrio,
      dir_calle: this.dir_calle,
      dir_nrocasa: this.dir_nrocasa,
      dir_referencia: this.dir_referencia,
      dir_celular: this.dir_celular,
      dir_telef: this.dir_telef,
      dir_predet: this.dir_predet,
    };

    this.guardando = true;

    const peticion = this.direccionEnEdicion
      ? this.direccionService.actualizar(this.direccionEnEdicion.dir_id, datos)
      : this.direccionService.crear(datos);

    peticion.subscribe({
      next: (resp) => {
        this.guardando = false;
        this.toastr.success(resp.message, 'Direcciones');
        this.cerrarFormulario();
        this.cargarDirecciones();
      },
      error: (err) => {
        this.guardando = false;
        const mensaje = err.error?.message || 'No se pudo guardar la dirección.';
        this.toastr.error(mensaje, 'Error');
      },
    });
  }

  eliminar(direccion: Direccion): void {
    if (!confirm('¿Eliminar esta dirección?')) return;

    this.direccionService.eliminar(direccion.dir_id).subscribe({
      next: (resp) => {
        this.toastr.success(resp.message, 'Direcciones');
        this.cargarDirecciones();
      },
      error: (err) => {
        const mensaje = err.error?.message || 'No se pudo eliminar la dirección.';
        this.toastr.error(mensaje, 'Error');
      },
    });
  }

  marcarPredeterminada(direccion: Direccion): void {
    this.direccionService.marcarPredeterminada(direccion.dir_id).subscribe({
      next: (resp) => {
        this.toastr.success(resp.message, 'Direcciones');
        this.cargarDirecciones();
      },
      error: (err) => {
        const mensaje = err.error?.message || 'No se pudo actualizar.';
        this.toastr.error(mensaje, 'Error');
      },
    });
  }
}