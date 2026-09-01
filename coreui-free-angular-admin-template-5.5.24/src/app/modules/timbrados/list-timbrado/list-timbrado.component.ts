import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Timbrado, SucursalOption, ComprobanteOption } from '../models/timbrado.model';
import { TimbradosService } from '../service/timbrados.service';
import { FormTimbradoComponent } from '../form-timbrado/form-timbrado.component';
import { DeleteTimbradoComponent } from '../delete-timbrado/delete-timbrado.component';

@Component({
  selector: 'app-list-timbrado',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule,
    FormTimbradoComponent, DeleteTimbradoComponent,
  ],
  templateUrl: './list-timbrado.component.html',
})
export class ListTimbradoComponent implements OnInit {
  timbrados: Timbrado[] = [];
  sucursales: SucursalOption[] = [];
  comprobantes: ComprobanteOption[] = [];
  cargando = false;

  search = '';
  filtroSucursal: number | null = null;
  filtroComprobante: number | null = null;
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  timbradoSeleccionado: Timbrado | null = null;

  constructor(
    private timbradosService: TimbradosService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.cargar();

    this.timbradosService.getConfig().subscribe({
      next: (res) => {
        this.sucursales = res.sucursales;
        this.comprobantes = res.comprobantes;
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

  cargar(): void {
    this.cargando = true;
    this.timbradosService
      .getAll({
        search: this.search,
        sucursal: this.filtroSucursal,
        comprobante: this.filtroComprobante,
        estado: this.filtroEstado,
      })
      .subscribe({
        next: (res) => {
          this.timbrados = res;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudieron cargar los timbrados', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroSucursal = null;
    this.filtroComprobante = null;
    this.filtroEstado = '';
    this.cargar();
  }

  // El backend ya resolvió la situación (Vigente, Vencido, Pendiente,
  // Agotado, Inactivo): acá solo se traduce a color.
  colorSituacion(situacion?: string): string {
    switch (situacion) {
      case 'Vigente':   return 'success';
      case 'Pendiente': return 'info';
      case 'Vencido':   return 'danger';
      case 'Agotado':   return 'warning';
      default:          return 'secondary';
    }
  }

  abrirNuevo(): void {
    this.timbradoSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(t: Timbrado): void {
    this.timbradoSeleccionado = { ...t };
    this.formModalVisible = true;
  }

  abrirEliminar(t: Timbrado): void {
    this.timbradoSeleccionado = t;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.timbradoSeleccionado?.tim_id) return;

    this.timbradosService.delete(this.timbradoSeleccionado.tim_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Timbrado eliminado', 'Exito');
        this.deleteModalVisible = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }

  cambiarEstado(t: Timbrado): void {
    this.timbradosService.toggleEstado(t.tim_id!).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Estado actualizado', 'Exito');
        this.cargar();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}
