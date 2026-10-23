import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import {
  BadgeModule, ButtonModule, CardModule, FormModule, PaginationModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import { MedioContacto, PanelConfig, PanelFila, PanelResumen } from '../models/panel-servicio.model';
import { PanelServiciosService, TipoConstancia } from '../service/panel-servicios.service';
import { ReasignarTecnicoComponent } from '../reasignar-tecnico/reasignar-tecnico.component';
import { RegistrarEntregaComponent } from '../registrar-entrega/registrar-entrega.component';
import { RegistrarRespuestaComponent } from '../registrar-respuesta/registrar-respuesta.component';
import { RegistrarContactoComponent } from '../registrar-contacto/registrar-contacto.component';
import { DetalleSolicitudComponent } from '../../solicitudes-servicio/detalle-solicitud/detalle-solicitud.component';

/** A3 - Panel general del servicio técnico (RF-040). */
@Component({
  selector: 'app-panel-general',
  standalone: true,
  imports: [
    CommonModule, FormsModule, BadgeModule, ButtonModule, CardModule, FormModule, PaginationModule, SpinnerModule, TableModule,
    ReasignarTecnicoComponent, RegistrarEntregaComponent, DetalleSolicitudComponent,
    RegistrarRespuestaComponent, RegistrarContactoComponent,
  ],
  templateUrl: './panel-general.component.html',
})
export class PanelGeneralComponent implements OnInit, OnDestroy {
  resumen: PanelResumen | null = null;
  config: PanelConfig = { estados_servicio: [], tecnicos: [], sucursales: [], canales: [], contacto_alerta_dias: 2 };
  filas: PanelFila[] = [];
  cargando = false;

  filtros = { buscar: '', ets_codigo: '', tec_id: '', suc_id: '', canal: '', solo_activas: true };
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
  respuestaVisible = false;
  contactoVisible = false;
  medioContacto: MedioContacto | null = null;

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
    this.filtros = { buscar: '', ets_codigo: '', tec_id: '', suc_id: '', canal: '', solo_activas: true };
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

  abrir(modal: 'reasignar' | 'entrega' | 'detalle' | 'respuesta' | 'contacto', f: PanelFila, medio: MedioContacto | null = null): void {
    this.seleccionada = f;
    this.medioContacto = medio;
    this.reasignarVisible = modal === 'reasignar';
    this.entregaVisible = modal === 'entrega';
    this.detalleVisible = modal === 'detalle';
    this.respuestaVisible = modal === 'respuesta';
    this.contactoVisible = modal === 'contacto';
  }

  /** D.13-b: abre WhatsApp y enseguida pide confirmar el contacto (el clic no garantiza el envío). */
  avisarWhatsapp(f: PanelFila): void {
    if (!f.whatsapp_url) return;
    window.open(f.whatsapp_url, '_blank', 'noopener');
    this.abrir('contacto', f, 'WHATSAPP');
  }

  pdfPresupuesto(f: PanelFila): void {
    this.service.pdfPresupuesto(f.sse_id).subscribe({
      next: (pdf) => this.service.abrirPdf(pdf, `presupuesto-${f.presupuesto?.numero ?? f.sse_numero}.pdf`),
      error: () => this.toastr.error('No se pudo generar el presupuesto', 'Error'),
    });
  }

  /** Días desde el último contacto (para resaltar los que superan la alerta). */
  sinContactoReciente(f: PanelFila): boolean {
    const dias = this.diasDesde(f.ultimo_contacto?.fecha);
    return dias === null || dias >= this.config.contacto_alerta_dias;
  }

  diasDesde(fecha: string | null | undefined): number | null {
    if (!fecha) return null;
    return Math.floor((Date.now() - new Date(fecha.replace(' ', 'T')).getTime()) / 86_400_000);
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
