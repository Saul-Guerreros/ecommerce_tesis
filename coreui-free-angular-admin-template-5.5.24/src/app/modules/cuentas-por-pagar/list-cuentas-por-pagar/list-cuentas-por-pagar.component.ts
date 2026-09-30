import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { CuentaPorPagar, ProveedorOption } from '../models/cuenta-por-pagar.model';
import { CuentasPorPagarService } from '../service/cuentas-por-pagar.service';
import { DetalleCuotasPagarComponent } from '../detalle-cuotas-pagar/detalle-cuotas-pagar.component';

@Component({
  selector: 'app-list-cuentas-por-pagar',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule, DetalleCuotasPagarComponent,
  ],
  templateUrl: './list-cuentas-por-pagar.component.html',
})
export class ListCuentasPorPagarComponent implements OnInit {
  facturas: CuentaPorPagar[] = [];
  proveedores: ProveedorOption[] = [];
  cargando = false;

  search = '';
  filtroProveedor: number | null = null;
  filtroEstado = '0';

  detalleVisible = false;
  facturaSeleccionada: CuentaPorPagar | null = null;

  private searchSubject = new Subject<string>();

  constructor(private service: CuentasPorPagarService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();
    this.service.getConfig().subscribe({
      next: (res) => (this.proveedores = res.proveedores),
      error: () => {},
    });
    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;
    this.service.getAll({ search: this.search, proveedor: this.filtroProveedor, estado: this.filtroEstado })
      .subscribe({
        next: (res) => { this.facturas = res; this.cargando = false; },
        error: () => { this.toastr.error('No se pudieron cargar las cuentas por pagar', 'Error'); this.cargando = false; },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroProveedor = null;
    this.filtroEstado = '0';
    this.cargar();
  }

  get totalPendienteGeneral(): number {
    return this.facturas.reduce((s, f) => s + Number(f.cuo_saldo), 0);
  }

  abrirDetalle(f: CuentaPorPagar): void {
    this.facturaSeleccionada = f;
    this.detalleVisible = true;
  }

  onPagoRegistrado(): void {
    this.cargar();
  }
}