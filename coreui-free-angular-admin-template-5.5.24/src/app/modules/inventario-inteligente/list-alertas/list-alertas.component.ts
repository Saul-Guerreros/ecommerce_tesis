// modules/inventario-inteligente/list-alertas/list-alertas.component.ts
import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, FormModule, PaginationModule, SpinnerModule, TableModule } from '@coreui/angular';
import { GsPipe } from '../../reportes/shared/gs.pipe';
import { InventarioInteligenteService } from '../service/inventario-inteligente.service';
import { AlertaStock, EstadoAlerta, NOMBRES_MODELO, TIPOS_ALERTA, TipoAlerta } from '../models/inventario-inteligente.model';
import { CerrarAlertaComponent, ModoCierre } from '../cerrar-alerta/cerrar-alerta.component';
import { DetallePronosticoComponent } from '../detalle-pronostico/detalle-pronostico.component';

/**
 * Lista de alertas. El backend devuelve el listado completo (acotado: una
 * alerta pendiente por producto/depósito) y se pagina en el cliente.
 */
@Component({
  selector: 'app-list-alertas',
  standalone: true,
  imports: [CommonModule, FormsModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    PaginationModule, GsPipe, CerrarAlertaComponent, DetallePronosticoComponent],
  templateUrl: './list-alertas.component.html',
  styles: [`.page-link { cursor: pointer; user-select: none; } .page-item.disabled .page-link { cursor: default; }`],
})
export class ListAlertasComponent implements OnInit, OnChanges {
  @Input() tipo: TipoAlerta | null = null;
  @Input() puedeGestionar = false;
  @Output() tipoChange = new EventEmitter<TipoAlerta | null>();
  @Output() cambio = new EventEmitter<void>();

  private service = inject(InventarioInteligenteService);
  private toastr = inject(ToastrService);

  readonly TIPOS = TIPOS_ALERTA;
  readonly tiposLista = Object.keys(TIPOS_ALERTA) as TipoAlerta[];
  readonly MODELOS = NOMBRES_MODELO;
  readonly porPagina = 15;

  alertas: AlertaStock[] = [];
  cargando = false;
  search = '';
  filtroEstado: EstadoAlerta = 1;
  paginaActual = 1;

  private searchSubject = new Subject<string>();

  cierreVisible = false;
  modoCierre: ModoCierre = 'atender';
  detalleVisible = false;
  seleccionada: AlertaStock | null = null;

  ngOnInit(): void {
    this.cargar();
    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar());
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['tipo'] && !changes['tipo'].firstChange) {
      this.cargar();
    }
  }

  // ---------------- Carga y filtros ----------------

  cargar(): void {
    this.cargando = true;
    this.service.getAlertas({ estado: this.filtroEstado, tipo: this.tipo, search: this.search }).subscribe({
      next: (res) => { this.alertas = res; this.paginaActual = 1; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar las alertas', 'Error'); this.cargando = false; },
    });
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cambiarTipo(tipo: TipoAlerta | null): void {
    this.tipo = tipo;
    this.tipoChange.emit(tipo);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = 1;
    this.cambiarTipo(null);
  }

  get esPendiente(): boolean {
    return this.filtroEstado === 1;
  }

  // ---------------- Paginación en el cliente ----------------

  get totalPaginas(): number {
    return Math.max(1, Math.ceil(this.alertas.length / this.porPagina));
  }

  get alertasPagina(): AlertaStock[] {
    const inicio = (this.paginaActual - 1) * this.porPagina;
    return this.alertas.slice(inicio, inicio + this.porPagina);
  }

  /** Rango de registros visibles ("Mostrando 16–30 de 144"). */
  get desde(): number {
    return this.alertas.length ? (this.paginaActual - 1) * this.porPagina + 1 : 0;
  }

  get hasta(): number {
    return Math.min(this.paginaActual * this.porPagina, this.alertas.length);
  }

  /** Hasta 7 números de página alrededor de la actual. */
  get paginas(): number[] {
    const desde = Math.max(1, Math.min(this.paginaActual - 3, this.totalPaginas - 6));
    const hasta = Math.min(this.totalPaginas, desde + 6);
    return Array.from({ length: hasta - desde + 1 }, (_, i) => desde + i);
  }

  irAPagina(pagina: number): void {
    if (pagina >= 1 && pagina <= this.totalPaginas) this.paginaActual = pagina;
  }

  // ---------------- Acciones ----------------

  verPronostico(a: AlertaStock): void {
    this.seleccionada = a;
    this.detalleVisible = true;
  }

  abrirCierre(a: AlertaStock, modo: ModoCierre): void {
    this.seleccionada = a;
    this.modoCierre = modo;
    this.cierreVisible = true;
  }

  confirmarCierre(observacion: string | null): void {
    if (!this.seleccionada) return;

    const peticion = this.modoCierre === 'atender'
      ? this.service.atender(this.seleccionada.id, observacion)
      : this.service.descartar(this.seleccionada.id, observacion ?? '');

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Alerta actualizada', 'Éxito');
        this.cierreVisible = false;
        this.cargar();
        this.cambio.emit();
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo actualizar la alerta', 'Error'),
    });
  }

  onDetalleActualizado(): void {
    this.cargar();
    this.cambio.emit();
  }
}
