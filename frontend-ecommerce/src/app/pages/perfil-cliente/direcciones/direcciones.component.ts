import { CommonModule } from '@angular/common';
import { Component, effect } from '@angular/core';
import { AuthService } from '../../auth/service/auth.service';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import {
  BarrioCatalogo,
  CalleCatalogo,
  CiudadCatalogo,
  Direccion,
} from '../../../core/models/direccion.model';
import { DireccionClienteService } from '../../../core/services/direccion-cliente.service';

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

  ciudades: CiudadCatalogo[] = [];
  barrios: BarrioCatalogo[] = [];
  calles: CalleCatalogo[] = [];

  barriosFiltrados: BarrioCatalogo[] = [];
  callesFiltradas: CalleCatalogo[] = [];

  mostrarFormulario = false;
  direccionEnEdicion: Direccion | null = null;

  ciudadId: number | null = null;
  dir_barid: number | null = null;
  dir_calid: number | null = null;
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
        this.ciudades = catalogos.ciudades;
        this.barrios = catalogos.barrios;
        this.calles = catalogos.calles;
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

  // ----- Cascada Ciudad -> Barrio -> Calle (calle depende de barrio, no de ciudad) -----

  onCiudadChange(): void {
    this.dir_barid = null;
    this.dir_calid = null;
    this.callesFiltradas = [];
    this.barriosFiltrados = this.ciudadId
      ? this.barrios.filter((b) => b.bar_ciuid === this.ciudadId)
      : [];
  }

  onBarrioChange(): void {
    this.dir_calid = null;
    this.callesFiltradas = this.dir_barid
      ? this.calles.filter((c) => c.cal_barid === this.dir_barid)
      : [];
  }

  abrirNuevo(): void {
    this.direccionEnEdicion = null;
    this.ciudadId = null;
    this.dir_barid = null;
    this.dir_calid = null;
    this.dir_nrocasa = '';
    this.dir_referencia = '';
    this.dir_celular = '';
    this.dir_telef = '';
    this.dir_predet = this.direcciones.length === 0;
    this.barriosFiltrados = [];
    this.callesFiltradas = [];
    this.mostrarFormulario = true;
  }

  abrirEditar(direccion: Direccion): void {
    this.direccionEnEdicion = direccion;

    // El listado solo trae nombres; para preseleccionar "ciudad" hay que
    // ubicar el barrio dentro del catálogo ya cargado.
    const barrio = this.barrios.find((b) => b.bar_id === direccion.dir_barid);
    this.ciudadId = barrio?.bar_ciuid ?? null;
    this.barriosFiltrados = this.ciudadId
      ? this.barrios.filter((b) => b.bar_ciuid === this.ciudadId)
      : [];

    this.dir_barid = direccion.dir_barid;
    this.callesFiltradas = this.calles.filter((c) => c.cal_barid === this.dir_barid);
    this.dir_calid = direccion.dir_calid;

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
    if (!this.dir_barid || !this.dir_calid) {
      this.toastr.warning('Seleccioná ciudad, barrio y calle.', 'Faltan datos');
      return;
    }

    const datos = {
      dir_barid: this.dir_barid,
      dir_calid: this.dir_calid,
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