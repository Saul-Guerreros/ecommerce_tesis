import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Deposito, SucursalOption } from '../models/deposito.model';
import { DepositosService } from '../service/depositos.service';
import { FormDepositoComponent } from '../form-deposito/form-deposito.component';
import { DeleteDepositoComponent } from '../delete-deposito/delete-deposito.component';

@Component({
  selector: 'app-list-deposito',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule,
    FormDepositoComponent, DeleteDepositoComponent,
  ],
  templateUrl: './list-deposito.component.html',
})
export class ListDepositoComponent implements OnInit {
  depositos: Deposito[] = [];
  sucursales: SucursalOption[] = [];
  cargando = false;

  search = '';
  filtroSucursal: number | null = null;
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  depositoSeleccionado: Deposito | null = null;

  constructor(
    private depositosService: DepositosService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.cargar();

    this.depositosService.getSucursales().subscribe({
      next: (res) => (this.sucursales = res),
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
    this.depositosService
      .getAll({ search: this.search, sucursal: this.filtroSucursal, estado: this.filtroEstado })
      .subscribe({
        next: (res) => {
          this.depositos = res;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudieron cargar los depósitos', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroSucursal = null;
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.depositoSeleccionado = null;
    this.formModalVisible = true;
  }

  abrirEditar(d: Deposito): void {
    this.depositoSeleccionado = { ...d };
    this.formModalVisible = true;
  }

  abrirEliminar(d: Deposito): void {
    this.depositoSeleccionado = d;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.depositoSeleccionado?.dpo_id) return;

    this.depositosService.delete(this.depositoSeleccionado.dpo_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Depósito eliminado', 'Éxito');
        this.deleteModalVisible = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }

  cambiarEstado(d: Deposito): void {
    this.depositosService.toggleEstado(d.dpo_id!).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito');
        this.cargar();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }

  marcarPrincipal(d: Deposito): void {
    this.depositosService.marcarPrincipal(d.dpo_id!).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Depósito principal actualizado', 'Éxito');
        this.cargar();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo marcar como principal', 'Error'),
    });
  }
}