import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Barrio, CiudadOption } from '../models/barrio.model';
import { BarriosService } from '../service/barrios.service';
import { FormBarrioComponent } from '../form-barrio/form-barrio.component';
import { DeleteBarrioComponent } from '../delete-barrio/delete-barrio.component';

@Component({
  selector: 'app-list-barrio',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule,
    FormBarrioComponent, DeleteBarrioComponent,
  ],
  templateUrl: './list-barrio.component.html',
})
export class ListBarrioComponent implements OnInit {
  barrios: Barrio[] = [];
  ciudades: CiudadOption[] = [];
  cargando = false;

  search = '';
  filtroCiudad: number | null = null;
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  barrioSeleccionado: Barrio | null = null;

  constructor(
    private barriosService: BarriosService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.cargar();

    this.barriosService.getConfig().subscribe({
      next: (res) => (this.ciudades = res),
      error: () => {},
    });

    // El buscador espera 400 ms sin tecleo antes de consultar, para no
    // disparar una peticion por cada letra.
    this.searchSubject
      .pipe(debounceTime(400), distinctUntilChanged())
      .subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;
    this.barriosService
      .getAll({ search: this.search, ciudad: this.filtroCiudad, estado: this.filtroEstado })
      .subscribe({
        next: (res) => {
          this.barrios = res;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudieron cargar los barrios', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroCiudad = null;
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.barrioSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(b: Barrio): void {
    this.barrioSeleccionado = { ...b };  // copia: no se muta la fila de la tabla
    this.formModalVisible = true;
  }

  abrirEliminar(b: Barrio): void {
    this.barrioSeleccionado = b;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.barrioSeleccionado?.bar_id) return;

    this.barriosService.delete(this.barrioSeleccionado.bar_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Barrio eliminado', 'Exito');
        this.deleteModalVisible = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }

  cambiarEstado(b: Barrio): void {
    this.barriosService.toggleEstado(b.bar_id!).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Estado actualizado', 'Exito');
        this.cargar();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}
