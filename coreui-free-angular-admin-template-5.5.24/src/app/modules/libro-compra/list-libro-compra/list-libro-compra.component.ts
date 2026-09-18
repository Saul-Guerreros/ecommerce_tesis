import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { LibroCompra, LibroCompraTotales, ProveedorOption } from '../models/libro-compra.model';
import { LibroCompraService } from '../service/libro-compra.service';

@Component({
  selector: 'app-list-libro-compra',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule],
  templateUrl: './list-libro-compra.component.html',
})
export class ListLibroCompraComponent implements OnInit {
  registros: LibroCompra[] = [];
  totales: LibroCompraTotales | null = null;
  proveedores: ProveedorOption[] = [];
  cargando = false;

  // Filtro por defecto: mes en curso.
  desde = this.primerDiaDelMes();
  hasta = this.hoy();
  filtroProveedor: number | null = null;
  filtroTipo = '';
  filtroEstado = '0'; // 0 = vigentes, 'todas' = incluye anuladas

  constructor(private service: LibroCompraService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();
    this.service.getConfig().subscribe({
      next: (res) => (this.proveedores = res.proveedores),
      error: () => {},
    });
  }

  cargar(): void {
    this.cargando = true;
    this.service
      .getAll({
        desde: this.desde,
        hasta: this.hasta,
        proveedor: this.filtroProveedor,
        tipo: this.filtroTipo,
        estado: this.filtroEstado,
      })
      .subscribe({
        next: (res) => {
          this.registros = res.data;
          this.totales = res.totales;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudo cargar el Libro de Compras', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.desde = this.primerDiaDelMes();
    this.hasta = this.hoy();
    this.filtroProveedor = null;
    this.filtroTipo = '';
    this.filtroEstado = '0';
    this.cargar();
  }

  private hoy(): string {
    return new Date().toISOString().substring(0, 10);
  }

  private primerDiaDelMes(): string {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().substring(0, 10);
  }
}