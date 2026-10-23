import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import {
  BadgeModule, ButtonModule, CardModule, FormModule, ModalModule, PaginationModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import { CobroConfig, CobroFila, CobroFiltros } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';
import { PagosSolicitudComponent } from '../pagos-solicitud/pagos-solicitud.component';

const FILTROS_INICIALES: CobroFiltros = { buscar: '', estado: 'PAGO_VERIFICAR', canal: '', mpa_id: '', suc_id: '', desde: '', hasta: '' };

/**
 * A4 - Bandeja "Cobros de servicios": pagos del servicio técnico, por
 * defecto los que esperan verificación (lo más viejo primero). "Gestionar"
 * abre la sección Pagos de la solicitud, donde se verifica o se cobra.
 */
@Component({
  selector: 'app-list-cobro',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule,
    FormModule, PaginationModule, ModalModule, PagosSolicitudComponent,
  ],
  templateUrl: './list-cobro.component.html',
})
export class ListCobroComponent implements OnInit, OnDestroy {
  filas: CobroFila[] = [];
  config: CobroConfig | null = null;
  cargando = false;

  filtros: CobroFiltros = { ...FILTROS_INICIALES };
  private busqueda = new Subject<void>();
  private busquedaSub?: Subscription;

  currentPage = 1;
  lastPage = 1;
  total = 0;
  rangoDesde = 0;
  rangoHasta = 0;

  seleccionada: CobroFila | null = null;
  gestionVisible = false;
  /** Hubo cambios dentro del modal: al cerrarlo se recarga la bandeja. */
  private huboCambios = false;

  constructor(private service: CobrosServicioService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.service.config().subscribe({
      next: (res) => (this.config = res.data),
      error: () => this.toastr.error('No se pudieron cargar los filtros', 'Error'),
    });
    this.cargar();
    this.busquedaSub = this.busqueda.pipe(debounceTime(400)).subscribe(() => this.cargar());
  }

  ngOnDestroy(): void {
    this.busquedaSub?.unsubscribe();
  }

  onBuscar(): void {
    this.busqueda.next();
  }

  cargar(page = 1): void {
    this.cargando = true;
    this.service.listar({ ...this.filtros, page }).subscribe({
      next: (res) => {
        this.filas = res.data;
        this.currentPage = res.meta.current_page;
        this.lastPage = res.meta.last_page;
        this.total = res.meta.total;
        this.rangoDesde = res.meta.desde;
        this.rangoHasta = res.meta.hasta;
        this.cargando = false;
      },
      error: () => { this.cargando = false; this.toastr.error('No se pudieron cargar los cobros', 'Error'); },
    });
  }

  limpiarFiltros(): void {
    this.filtros = { ...FILTROS_INICIALES };
    this.cargar();
  }

  get pagesArray(): number[] {
    const ventana = 5;
    let inicio = Math.max(1, this.currentPage - Math.floor(ventana / 2));
    const fin = Math.min(this.lastPage, inicio + ventana - 1);
    inicio = Math.max(1, fin - ventana + 1);
    return Array.from({ length: fin - inicio + 1 }, (_, i) => inicio + i);
  }

  cambiarPagina(page: number): void {
    if (page < 1 || page > this.lastPage || page === this.currentPage) return;
    this.cargar(page);
  }

  colorEstado(codigo: string | null): string {
    return ({ PAGO_VERIFICAR: 'primary', CONFIRMADO: 'success', RECHAZADO: 'danger' } as Record<string, string>)[codigo ?? ''] ?? 'secondary';
  }

  gestionar(fila: CobroFila): void {
    this.seleccionada = fila;
    this.huboCambios = false;
    this.gestionVisible = true;
  }

  onCambio(): void {
    this.huboCambios = true;
  }

  cerrarGestion(visible: boolean): void {
    this.gestionVisible = visible;
    if (!visible && this.huboCambios) this.cargar(this.currentPage);
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}
