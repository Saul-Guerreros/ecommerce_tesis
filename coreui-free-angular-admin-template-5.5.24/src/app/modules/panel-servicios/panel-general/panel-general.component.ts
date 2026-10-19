import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import {
  BadgeModule, ButtonModule, CardModule, FormModule, PaginationModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import { PanelConfig, PanelFila, PanelResumen } from '../models/panel-servicio.model';
import { PanelServiciosService, TipoConstancia } from '../service/panel-servicios.service';
import { ReasignarTecnicoComponent } from '../reasignar-tecnico/reasignar-tecnico.component';
import { RegistrarEntregaComponent } from '../registrar-entrega/registrar-entrega.component';
import { DetalleSolicitudComponent } from '../../solicitudes-servicio/detalle-solicitud/detalle-solicitud.component';

/** A3 - Panel general del servicio técnico (RF-040). */
@Component({
  selector: 'app-panel-general',
  standalone: true,
  imports: [
    CommonModule, FormsModule, BadgeModule, ButtonModule, CardModule, FormModule, PaginationModule, SpinnerModule, TableModule,
    ReasignarTecnicoComponent, RegistrarEntregaComponent, DetalleSolicitudComponent,
  ],
  templateUrl: './panel-general.component.html',
})
export class PanelGeneralComponent implements OnInit, OnDestroy {
  resumen: PanelResumen | null = null;
  config: PanelConfig = { estados_servicio: [], tecnicos: [] };
  filas: PanelFila[] = [];
  cargando = false;

  filtros = { buscar: '', ets_codigo: '', tec_id: '', solo_activas: true };
  private searchSubject = new Subject<void>();
  private searchSub?: Subscription;

  currentPage = 1;
  lastPage = 1;
  total = 0;
  rangoDesde = 0;
  rangoHasta = 0;

  seleccionada: PanelFila | null = null;
  reasignarVisible = false;
  entregaVisible = false;
  detalleVisible = false;

  constructor(private service: PanelServiciosService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.service.config().subscribe({ next: (c) => (this.config = c) });
    this.refrescar();
    this.searchSub = this.searchSubject.pipe(debounceTime(400)).subscribe(() => this.cargar());
  }

  ngOnDestroy(): void {
    this.searchSub?.unsubscribe();
  }

  refrescar(): void {
    this.cargarResumen();
    this.cargar(this.currentPage);
  }

  cargarResumen(): void {
    this.service.resumen().subscribe({ next: (r) => (this.resumen = r.data) });
  }

  onSearchChange(): void {
    this.searchSubject.next();
  }

  /** Las tarjetas funcionan como atajos de filtro. */
  filtrarPorEstado(codigo: string): void {
    this.filtros.ets_codigo = codigo;
    this.cargar();
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
      error: () => { this.toastr.error('No se pudo cargar el panel', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void {
    this.filtros = { buscar: '', ets_codigo: '', tec_id: '', solo_activas: true };
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

  abrir(modal: 'reasignar' | 'entrega' | 'detalle', f: PanelFila): void {
    this.seleccionada = f;
    this.reasignarVisible = modal === 'reasignar';
    this.entregaVisible = modal === 'entrega';
    this.detalleVisible = modal === 'detalle';
  }

  constancia(f: PanelFila, tipo: TipoConstancia): void {
    this.service.constancia(f.sse_id, tipo).subscribe({
      next: (pdf) => this.service.abrirPdf(pdf, `constancia-${tipo}-${f.sse_numero}.pdf`),
      error: () => this.toastr.error('No se pudo generar la constancia', 'Error'),
    });
  }

  colorEstado(codigo: string): string {
    switch (codigo) {
      case 'SOLICITADA': return 'warning';
      case 'PRESUPUESTADA': return 'secondary';
      case 'LISTA': return 'success';
      case 'ENTREGADA': return 'dark';
      case 'CANCELADA': return 'light';
      default: return 'info';
    }
  }
}
