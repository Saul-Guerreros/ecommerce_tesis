import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import {
  AlertModule, BadgeModule, ButtonModule, CardModule,
  FormModule, PaginationModule, SpinnerModule, TableModule,
} from '@coreui/angular';

import {
  CampoBusquedaCredito, CampoBusquedaOption, EstadoSolicitudOption,
  SolicitudCreditoFiltros, SolicitudCreditoLista,
} from '../models/solicitud-credito.model';
import { SolicitudesCreditoService } from '../service/solicitudes-credito.service';

@Component({
  selector: 'app-list-solicitud-credito',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule, PaginationModule, AlertModule,
  ],
  templateUrl: './list-solicitud-credito.component.html',
})
export class ListSolicitudCreditoComponent implements OnInit {
  solicitudes: SolicitudCreditoLista[] = [];
  cargando = false;

  // Catálogos
  estados: EstadoSolicitudOption[] = [];
  camposBusqueda: CampoBusquedaOption[] = [];

  /**
   * Búsqueda con combo + una caja: el administrador elige el criterio y
   * escribe una sola vez. Distinto de Pedidos, que usa tres cajas
   * excluyentes — acá hay tres criterios y el combo ocupa menos.
   */
  campo: CampoBusquedaCredito = 'numero';
  termino = '';

  // Filtros
  filtrosVisibles = true;
  filtroEstado: number | null = null;
  desde = '';
  hasta = '';

  // Paginación
  currentPage = 1;
  lastPage = 1;
  perPage = 20;
  total = 0;
  rangoDesde = 0;
  rangoHasta = 0;

  pendientes = 0;

  private busquedaSubject = new Subject<void>();

  constructor(
    private service: SolicitudesCreditoService,
    private toastr: ToastrService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.cargar();

    this.service.config().subscribe({
      next: (res) => {
        this.estados = res.estados;
        this.camposBusqueda = res.campos_busqueda;
      },
      error: () => this.toastr.error('No se pudieron cargar los filtros', 'Error'),
    });

    // Búsqueda automática con retraso: evita una consulta por tecla.
    this.busquedaSubject
      .pipe(debounceTime(400), distinctUntilChanged())
      .subscribe(() => this.cargar(1));
  }

  // ---------- Búsqueda ----------

  onBusquedaChange(): void {
    this.busquedaSubject.next();
  }

  /** Enter ejecuta la búsqueda de inmediato, sin esperar el debounce. */
  buscarAhora(): void {
    this.cargar(1);
  }

  /** Cambiar de criterio con la caja vacía no debe disparar una consulta. */
  onCampoChange(): void {
    if (this.termino.trim()) {
      this.cargar(1);
    }
  }

  get placeholderBusqueda(): string {
    switch (this.campo) {
      case 'ci':      return '1234567';
      case 'cliente': return 'Juan Pérez';
      default:        return 'SC-000127';
    }
  }

  // ---------- Fechas ----------

  get rangoFechasInvalido(): boolean {
    return !!this.desde && !!this.hasta && this.hasta < this.desde;
  }

  // ---------- Carga ----------

  private filtrosActuales(page?: number): SolicitudCreditoFiltros {
    const termino = this.termino.trim();

    return {
      campo: termino ? this.campo : undefined,
      q: termino || undefined,
      estado: this.filtroEstado,
      desde: this.desde || undefined,
      hasta: this.hasta || undefined,
      page: page ?? this.currentPage,
      per_page: this.perPage,
    };
  }

  cargar(page: number = this.currentPage): void {
    if (this.rangoFechasInvalido) {
      this.toastr.error('La fecha "Hasta" no puede ser anterior a "Desde"', 'Rango inválido');
      return;
    }

    this.cargando = true;

    this.service.list(this.filtrosActuales(page)).subscribe({
      next: (res) => {
        this.solicitudes = res.data;
        this.currentPage = res.meta.current_page;
        this.lastPage = res.meta.last_page;
        this.perPage = res.meta.per_page;
        this.total = res.meta.total;
        this.rangoDesde = res.meta.desde;
        this.rangoHasta = res.meta.hasta;
        this.pendientes = res.meta.pendientes;
        this.cargando = false;
      },
      error: (err) => {
        const mensaje =
          err?.error?.errors?.q?.[0] ??
          err?.error?.message ??
          'No se pudieron cargar las solicitudes';
        this.toastr.error(mensaje, 'Error');
        this.cargando = false;
      },
    });
  }

  aplicar(): void {
    this.cargar(1);
  }

  limpiar(): void {
    this.campo = 'numero';
    this.termino = '';
    this.filtroEstado = null;
    this.desde = '';
    this.hasta = '';
    this.cargar(1);
  }

  /** Clic en la alerta: filtra por las solicitudes sin evaluar. */
  verPendientes(): void {
    const recibida = this.estados.find((e) => e.esc_codigo === 'PENDIENTE');
    if (!recibida) return;

    this.filtroEstado = recibida.esc_id;
    this.filtrosVisibles = true;
    this.cargar(1);
  }

  // ---------- Paginación ----------

  /** Ventana deslizante de 5 páginas para no romper el layout. */
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

  // ---------- Navegación ----------

  verSolicitud(solicitud: SolicitudCreditoLista): void {
    this.router.navigate(['/solicitudes-credito', solicitud.scr_id]);
  }

  // ---------- Presentación ----------

  colorEstado(codigo: string | null): string {
    switch (codigo) {
      case 'APROBADA':    return 'success';
      case 'ACEPTADA':    return 'primary';
      case 'EN_REVISION': return 'info';
      case 'RECHAZADA':   return 'danger';
      case 'PENDIENTE':   return 'warning';
      default:            return 'secondary';
    }
  }

  /** Guaraníes: separador de miles y sin decimales. */
  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}