import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import {
  BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import { ClienteOption, CuentaPorCobrar, MetodoPagoOption } from '../models/cuenta-por-cobrar.model';
import { CuentasPorCobrarService } from '../service/cuentas-por-cobrar.service';
import { DetalleCuotasCobrarComponent } from '../detalle-cuotas-cobrar/detalle-cuotas-cobrar.component';

@Component({
  selector: 'app-list-cuentas-por-cobrar',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule, DetalleCuotasCobrarComponent,
  ],
  templateUrl: './list-cuentas-por-cobrar.component.html',
})
export class ListCuentasPorCobrarComponent implements OnInit {
  facturas: CuentaPorCobrar[] = [];
  clientes: ClienteOption[] = [];
  /** Se cargan acá y se pasan al modal: una sola llamada de config. */
  metodos: MetodoPagoOption[] = [];
  cargando = false;

  totales = { facturas: 0, saldo: 0, vencidas: 0 };

  search = '';
  filtroCliente: number | null = null;
  filtroEstado = 'pendientes';
  desde = '';
  hasta = '';

  detalleVisible = false;
  facturaSeleccionada: CuentaPorCobrar | null = null;

  private searchSubject = new Subject<string>();

  constructor(
    private service: CuentasPorCobrarService,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.cargar();

    this.service.getConfig().subscribe({
      next: (res) => {
        this.clientes = res.clientes;
        this.metodos = res.metodos;
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

    this.service.getAll({
      search: this.search,
      cliente: this.filtroCliente,
      estado: this.filtroEstado,
      desde: this.desde,
      hasta: this.hasta,
    }).subscribe({
      next: (res) => {
        this.facturas = res.data;
        this.totales = res.totales;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar las cuentas por cobrar', 'Error');
        this.cargando = false;
      },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroCliente = null;
    this.filtroEstado = 'pendientes';
    this.desde = '';
    this.hasta = '';
    this.cargar();
  }

  abrirDetalle(f: CuentaPorCobrar): void {
    this.facturaSeleccionada = f;
    this.detalleVisible = true;
  }

  onCobroRegistrado(): void {
    this.cargar();
  }
}