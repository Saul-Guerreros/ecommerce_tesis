import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { MovimientoStock, ProductoOption, DepositoOption } from '../models/movimiento-stock.model';
import { KardexService } from '../service/movimiento_stock.service';

@Component({
  selector: 'app-list-kardex',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule],
  templateUrl: './list-movimiento_stock.component.html',
})
export class ListKardexComponent implements OnInit {
  movimientos: MovimientoStock[] = [];
  productos: ProductoOption[] = [];
  depositos: DepositoOption[] = [];
  cargando = false;

  filtroProducto: number | null = null;
  filtroDeposito: number | null = null;
  filtroTipo = '';
  filtroDesde = '';
  filtroHasta = '';

  constructor(private service: KardexService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();

    this.service.getConfig().subscribe({
      next: (res) => { this.productos = res.productos; this.depositos = res.depositos; },
      error: () => {},
    });
  }

  cargar(): void {
    this.cargando = true;
    this.service
      .getAll({
        producto: this.filtroProducto,
        deposito: this.filtroDeposito,
        tipo: this.filtroTipo,
        desde: this.filtroDesde,
        hasta: this.filtroHasta,
      })
      .subscribe({
        next: (res) => { this.movimientos = res; this.cargando = false; },
        error: () => { this.toastr.error('No se pudo cargar el kardex', 'Error'); this.cargando = false; },
      });
  }

  limpiarFiltros(): void {
    this.filtroProducto = null;
    this.filtroDeposito = null;
    this.filtroTipo = '';
    this.filtroDesde = '';
    this.filtroHasta = '';
    this.cargar();
  }
  etiquetaTipo(tipo: string): string {
  switch (tipo) {
    case 'E': return 'Entrada';
    case 'S': return 'Salida';
    case 'A': return 'Ajuste (NC)';
    default:  return tipo;
  }
}

colorTipo(tipo: string): string {
  switch (tipo) {
    case 'E': return 'success';
    case 'S': return 'danger';
    case 'A': return 'warning';
    default:  return 'secondary';
  }
}
}