import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import {
  BadgeModule, ButtonModule, CardModule, FormModule, ModalModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import { EstadoOption, Venta } from '../models/venta.model';
import { VentasService } from '../service/ventas.service';
import { VerVentaComponent } from '../ver-venta/ver-venta.component';

@Component({
  selector: 'app-list-venta',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule, ModalModule, VerVentaComponent,
  ],
  templateUrl: './list-venta.component.html',
})
export class ListVentaComponent implements OnInit {
  ventas: Venta[] = [];
  estados: EstadoOption[] = [];
  cargando = false;
  cargandoVer = false;

  search = '';
  filtroEstado = '';
  filtroDesde = '';
  filtroHasta = '';

  private searchSubject = new Subject<string>();

  verModalVisible = false;
  ventaSeleccionada: Venta | null = null;

  anularModalVisible = false;
  ventaAAnular: Venta | null = null;
  motivoAnulacion = '';
  anulando = false;

  constructor(private service: VentasService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();

    this.service.getConfig().subscribe({
      next: (res) => (this.estados = res.estados),
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

    this.service
      .getAll({
        search: this.search,
        estado: this.filtroEstado,
        desde: this.filtroDesde,
        hasta: this.filtroHasta,
      })
      .subscribe({
        next: (res) => { this.ventas = res; this.cargando = false; },
        error: () => {
          this.toastr.error('No se pudieron cargar las facturas de venta', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.filtroDesde = '';
    this.filtroHasta = '';
    this.cargar();
  }

  /**
   * El listado no trae el detalle (seria N+1 de lineas para nada): se
   * pide la factura completa recien al abrir el modal.
   */
  abrirVer(v: Venta): void {
    this.cargandoVer = true;

    this.service.getById(v.ven_id).subscribe({
      next: (completa) => {
        this.ventaSeleccionada = completa;
        this.verModalVisible = true;
        this.cargandoVer = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar el detalle de la factura', 'Error');
        this.cargandoVer = false;
      },
    });
  }

  // Se dispara desde el listado o desde el footer del modal Ver.
  pedirAnular(v: Venta): void {
    this.ventaAAnular = v;
    this.motivoAnulacion = '';
    this.anularModalVisible = true;
  }

  cancelarAnular(): void {
    this.anularModalVisible = false;
    this.ventaAAnular = null;
  }

  confirmarAnular(): void {
    if (!this.ventaAAnular || this.anulando) return;

    this.anulando = true;

    this.service.anular(this.ventaAAnular.ven_id, this.motivoAnulacion).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Factura anulada', 'Éxito');
        this.anularModalVisible = false;
        this.verModalVisible = false;
        this.anulando = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo anular la factura', 'Error');
        this.anulando = false;
      },
    });
  }

  colorEstado(estado: number): string {
    if (estado === 9) return 'secondary';
    if (estado === 2) return 'info';
    return 'success';
  }

  textoEstado(estado: number): string {
    const encontrado = this.estados.find((e) => e.valor === estado);
    return encontrado?.descripcion ?? '—';
  }

  identificacion(v: Venta): string {
    return v.ven_ruc ? `RUC ${v.ven_ruc}` : `CI ${v.ven_ci}`;
  }
}