import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { StockItem, ProductoOption, DepositoOption, MarcaOption } from '../models/stock.model';
import { StockService } from '../service/stock.service';

@Component({
  selector: 'app-list-stock',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule],
  templateUrl: './list-stock.component.html',
})
export class ListStockComponent implements OnInit {
  stock: StockItem[] = [];
  productos: ProductoOption[] = [];
  depositos: DepositoOption[] = [];
  marcas: MarcaOption[] = [];
  cargando = false;

  filtroProducto: number | null = null;
  filtroDeposito: number | null = null;
  filtroMarca: number | null = null;
  soloBajoMinimo = false;

  constructor(private service: StockService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();

    this.service.getConfig().subscribe({
      next: (res) => { this.productos = res.productos; this.depositos = res.depositos; this.marcas = res.marcas; },
      error: () => {},
    });
  }

  cargar(): void {
    this.cargando = true;
    this.service
      .getAll({
        producto: this.filtroProducto,
        deposito: this.filtroDeposito,
        marca: this.filtroMarca,
        bajoMinimo: this.soloBajoMinimo,
      })
      .subscribe({
        next: (res) => { this.stock = res; this.cargando = false; },
        error: () => { this.toastr.error('No se pudo cargar el stock', 'Error'); this.cargando = false; },
      });
  }

  limpiarFiltros(): void {
    this.filtroProducto = null;
    this.filtroDeposito = null;
    this.filtroMarca = null;
    this.soloBajoMinimo = false;
    this.cargar();
  }

  get valorTotalInventario(): number {
    return this.stock.reduce((suma, s) => suma + (s.valorizado || 0), 0);
  }
}