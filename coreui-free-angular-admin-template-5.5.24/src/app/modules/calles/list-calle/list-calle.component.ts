import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Calle, CiudadOption, BarrioOption } from '../models/calle.model';
import { CallesService } from '../service/calles.service';
import { FormCalleComponent } from '../form-calle/form-calle.component';
import { DeleteCalleComponent } from '../delete-calle/delete-calle.component';

@Component({
  selector: 'app-list-calle',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule,
    FormCalleComponent, DeleteCalleComponent,
  ],
  templateUrl: './list-calle.component.html',
})
export class ListCalleComponent implements OnInit {
  calles: Calle[] = [];
  ciudades: CiudadOption[] = [];
  private todosLosBarrios: BarrioOption[] = [];
  barriosFiltrados: BarrioOption[] = [];
  cargando = false;

  search = '';
  filtroCiudad: number | null = null;
  filtroBarrio: number | null = null;
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  calleSeleccionada: Calle | null = null;

  constructor(
    private callesService: CallesService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.cargar();

    this.callesService.getConfig().subscribe({
      next: (res) => {
        this.ciudades        = res.ciudades;
        this.todosLosBarrios = res.barrios;
      },
      error: () => {},
    });

    this.searchSubject
      .pipe(debounceTime(400), distinctUntilChanged())
      .subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  /**
   * El filtro de barrio depende del de ciudad: al cambiar la ciudad se
   * recalculan los barrios disponibles y se descarta el barrio elegido.
   */
  onFiltroCiudadChange(): void {
    this.filtroBarrio = null;
    this.barriosFiltrados = this.filtroCiudad
      ? this.todosLosBarrios.filter((b) => b.bar_ciuid === Number(this.filtroCiudad))
      : [];
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.callesService
      .getAll({
        search: this.search,
        ciudad: this.filtroCiudad,
        barrio: this.filtroBarrio,
        estado: this.filtroEstado,
      })
      .subscribe({
        next: (res) => {
          this.calles = res;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudieron cargar las calles', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroCiudad = null;
    this.filtroBarrio = null;
    this.filtroEstado = '';
    this.barriosFiltrados = [];
    this.cargar();
  }

  abrirNuevo(): void {
    this.calleSeleccionada = null;
    this.formModalVisible = true;
  }

  abrirEditar(c: Calle): void {
    this.calleSeleccionada = { ...c };
    this.formModalVisible = true;
  }

  abrirEliminar(c: Calle): void {
    this.calleSeleccionada = c;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.calleSeleccionada?.cal_id) return;

    this.callesService.delete(this.calleSeleccionada.cal_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Calle eliminada', 'Exito');
        this.deleteModalVisible = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }

  cambiarEstado(c: Calle): void {
    this.callesService.toggleEstado(c.cal_id!).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Estado actualizado', 'Exito');
        this.cargar();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}
