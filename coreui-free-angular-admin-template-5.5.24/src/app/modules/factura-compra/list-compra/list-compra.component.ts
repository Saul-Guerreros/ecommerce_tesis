import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Subject, debounceTime } from 'rxjs';
import { ButtonModule, BadgeModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import { CompraCab, ProveedorOption } from '../models/compra.model';
import { ComprasService } from '../service/compras.service';
import { AnularCompraComponent } from '../anular-compra/anular-compra.component';

@Component({
  selector: 'app-list-compra',
  standalone: true,
  imports: [CommonModule, FormsModule, ButtonModule, BadgeModule, CardModule, FormModule, SpinnerModule, TableModule, AnularCompraComponent],
  templateUrl: './list-compra.component.html',
})
export class ListCompraComponent implements OnInit {
  compras: CompraCab[] = [];
  proveedores: ProveedorOption[] = [];
  cargando = false;

  search = '';
  filtroProveedor: number | null = null;
  filtroEstado = '';

  anularModalVisible = false;
  compraSeleccionada: CompraCab | null = null;

  private buscar$ = new Subject<void>();

  constructor(
    private service: ComprasService,
    private router: Router,
    private toastr: ToastrService
  ) {
    this.buscar$.pipe(debounceTime(400)).subscribe(() => this.cargar());
  }

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (res) => (this.proveedores = res.proveedores),
      error: () => this.toastr.error('No se pudieron cargar los proveedores', 'Error'),
    });
    this.cargar();
  }

  onSearchChange(): void {
    this.buscar$.next();
  }

  cargar(): void {
    this.cargando = true;
    this.service
      .getAll({ search: this.search, proveedor: this.filtroProveedor, estado: this.filtroEstado })
      .subscribe({
        next: (res) => {
          this.compras = res;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudieron cargar las compras', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroProveedor = null;
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNueva(): void {
    this.router.navigate(['/compras/nueva']);
  }

  abrirVer(c: CompraCab): void {
    this.router.navigate(['/compras', c.com_id]);
  }

  abrirAnular(c: CompraCab): void {
    this.compraSeleccionada = c;
    this.anularModalVisible = true;
  }

  confirmarAnulada(): void {
    this.cargar();
  }
}