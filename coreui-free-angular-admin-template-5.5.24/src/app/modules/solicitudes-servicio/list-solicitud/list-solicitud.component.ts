import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import {
  BadgeModule, ButtonModule, CardModule, FormModule, PaginationModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import { SolicitudesConfig, SolicitudFiltros, SolicitudServicioLista } from '../models/solicitud-servicio.model';
import { SolicitudesServicioService } from '../service/solicitudes-servicio.service';
import { DetalleSolicitudComponent } from '../detalle-solicitud/detalle-solicitud.component';
import { AgendarRetiroComponent } from '../agendar-retiro/agendar-retiro.component';
import { RechazarSolicitudComponent } from '../rechazar-solicitud/rechazar-solicitud.component';
import { ConfirmarRecepcionComponent } from '../confirmar-recepcion/confirmar-recepcion.component';

/** A1 - Bandeja de solicitudes (contenedor de los modales A2, agendar y rechazar). */
@Component({
  selector: 'app-list-solicitud',
  standalone: true,
  imports: [
    CommonModule, FormsModule, RouterLink, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule,
    FormModule, PaginationModule,
    DetalleSolicitudComponent, AgendarRetiroComponent, RechazarSolicitudComponent, ConfirmarRecepcionComponent,
  ],
  templateUrl: './list-solicitud.component.html',
})
export class ListSolicitudComponent implements OnInit, OnDestroy {
  solicitudes: SolicitudServicioLista[] = [];
  config: SolicitudesConfig = { estados_servicio: [], estados_ingreso: [], tipos_entrega: [], tecnicos: [], sucursales: [], canales: [] };
  cargando = false;
  procesandoId: number | null = null;

  filtros: SolicitudFiltros = { buscar: '', ets_id: '', elg_id: '', ten_id: '', garantia: '', suc_id: '', canal: '', solo_pendientes: true };
  private searchSubject = new Subject<void>();
  private searchSub?: Subscription;

  currentPage = 1;
  lastPage = 1;
  total = 0;
  rangoDesde = 0;
  rangoHasta = 0;

  seleccionada: SolicitudServicioLista | null = null;
  detalleVisible = false;
  agendarVisible = false;
  rechazarVisible = false;
  recepcionVisible = false;

  constructor(private service: SolicitudesServicioService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (cfg) => (this.config = cfg),
      error: () => this.toastr.error('No se pudieron cargar los filtros', 'Error'),
    });
    this.cargar();
    this.searchSub = this.searchSubject.pipe(debounceTime(400)).subscribe(() => this.cargar());
  }

  ngOnDestroy(): void {
    this.searchSub?.unsubscribe();
  }

  onSearchChange(): void {
    this.searchSubject.next();
  }

  cargar(page = 1): void {
    this.cargando = true;
    this.service.getAll({ ...this.filtros, page }).subscribe({
      next: (res) => {
        this.solicitudes = res.data;
        this.currentPage = res.meta.current_page;
        this.lastPage = res.meta.last_page;
        this.total = res.meta.total;
        this.rangoDesde = res.meta.desde;
        this.rangoHasta = res.meta.hasta;
        this.cargando = false;
      },
      error: () => { this.toastr.error('No se pudieron cargar las solicitudes', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void {
    this.filtros = { buscar: '', ets_id: '', elg_id: '', ten_id: '', garantia: '', suc_id: '', canal: '', solo_pendientes: true };
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

  // ---------- Reglas de botones (espejo de las transiciones del backend) ----------

  esSolicitada(s: SolicitudServicioLista): boolean {
    return s.estado_servicio.codigo === 'SOLICITADA';
  }

  puedeAgendar(s: SolicitudServicioLista): boolean {
    return this.esSolicitada(s) && s.a_domicilio && ['PENDIENTE', 'RETIRO_AGENDADO'].includes(s.estado_ingreso.codigo ?? '');
  }

  puedeTransito(s: SolicitudServicioLista): boolean {
    return this.esSolicitada(s) && s.estado_ingreso.codigo === 'RETIRO_AGENDADO';
  }

  puedeRecibir(s: SolicitudServicioLista): boolean {
    if (!this.esSolicitada(s)) return false;
    return s.a_domicilio ? s.estado_ingreso.codigo !== 'PENDIENTE' : true;
  }

  colorServicio(codigo: string | null): string {
    switch (codigo) {
      case 'SOLICITADA': return 'warning';
      case 'CANCELADA': return 'secondary';
      case 'ENTREGADA': return 'success';
      default: return 'info';
    }
  }

  // ---------- Acciones ----------

  abrir(modal: 'detalle' | 'agendar' | 'rechazar' | 'recepcion', s: SolicitudServicioLista): void {
    this.seleccionada = s;
    this.detalleVisible = modal === 'detalle';
    this.agendarVisible = modal === 'agendar';
    this.rechazarVisible = modal === 'rechazar';
    this.recepcionVisible = modal === 'recepcion';
  }

  marcarEnTransito(s: SolicitudServicioLista): void {
    this.procesandoId = s.sse_id;
    this.service.enTransito(s.sse_id).subscribe({
      next: (res) => { this.toastr.success(res.message, 'Éxito'); this.procesandoId = null; this.cargar(this.currentPage); },
      error: (err) => {
        this.procesandoId = null;
        const msg = err?.error?.errors ? Object.values(err.error.errors as Record<string, string[]>)[0]?.[0] : null;
        this.toastr.error(msg ?? err?.error?.message ?? 'No se pudo actualizar', 'Error');
      },
    });
  }

  onGuardado(): void {
    this.cargar(this.currentPage);
  }
}
