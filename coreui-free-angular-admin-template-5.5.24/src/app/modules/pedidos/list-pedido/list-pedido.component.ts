import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import {
  AlertModule, BadgeModule, ButtonModule, CardModule,
  DropdownDividerDirective, DropdownItemDirective,
  DropdownMenuDirective, DropdownToggleDirective,
  FormModule, PaginationModule, SpinnerModule, TableModule,
} from '@coreui/angular';

import {
  CampoBusqueda, EstadoPagoOption, EstadoPedidoOption, MetodoPagoOption,
  PedidoFiltros, PedidoLista, TipoEntregaOption,
} from '../models/pedido.model';
import { PedidosService } from '../service/pedidos.service';

@Component({
  selector: 'app-list-pedido',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule, PaginationModule, AlertModule,
    DropdownToggleDirective, DropdownMenuDirective,
    DropdownItemDirective, DropdownDividerDirective,
  ],
  templateUrl: './list-pedido.component.html',
})
export class ListPedidoComponent implements OnInit {
  pedidos: PedidoLista[] = [];
  cargando = false;
  exportando = false;

  // Catálogos
  estadosPedido: EstadoPedidoOption[] = [];
  estadosPago: EstadoPagoOption[] = [];
  tiposEntrega: TipoEntregaOption[] = [];
  metodosPago: MetodoPagoOption[] = [];

  // Búsqueda (excluyente)
  busNumero = '';
  busCedula = '';
  busCliente = '';

  // Filtros
  filtrosVisibles = true;
  filtroEstadoPedido: number | null = null;
  filtroEstadoPago: number | null = null;
  filtroTipoEntrega: number | null = null;
  filtroMetodoPago: number | null = null;
  desde = '';
  hasta = '';
  filtroRetiroVencido = false;

  // Paginación
  currentPage = 1;
  lastPage = 1;
  perPage = 20;
  total = 0;
  rangoDesde = 0;
  rangoHasta = 0;

  pagosPendientes = 0;
  retirosVencidos = 0;

  private busquedaSubject = new Subject<void>();

  constructor(
    private service: PedidosService,
    private toastr: ToastrService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.cargar();

    this.service.config().subscribe({
      next: (res) => {
        this.estadosPedido = res.estados_pedido;
        this.estadosPago = res.estados_pago;
        this.tiposEntrega = res.tipos_entrega;
        this.metodosPago = res.metodos_pago;
      },
      error: () => this.toastr.error('No se pudieron cargar los filtros', 'Error'),
    });

    // Búsqueda automática con retraso: evita una consulta por tecla.
    this.busquedaSubject
      .pipe(debounceTime(400), distinctUntilChanged())
      .subscribe(() => this.cargar(1));
  }

  // ---------- Búsqueda excluyente ----------

  /** Devuelve el único campo que tiene contenido, o null si están todos vacíos. */
  get campoActivo(): CampoBusqueda | null {
    if (this.busNumero.trim()) return 'num';
    if (this.busCedula.trim()) return 'ci';
    if (this.busCliente.trim()) return 'cliente';
    return null;
  }

  /** Un campo se deshabilita solo si OTRO campo ya tiene contenido. */
  deshabilitado(campo: CampoBusqueda): boolean {
    const activo = this.campoActivo;
    return activo !== null && activo !== campo;
  }

  onBusquedaChange(): void {
    this.busquedaSubject.next();
  }

  /** Enter ejecuta la búsqueda de inmediato, sin esperar el debounce. */
  buscarAhora(): void {
    if (!this.campoActivo) return;
    this.cargar(1);
  }

  // ---------- Fechas ----------

  get rangoFechasInvalido(): boolean {
    return !!this.desde && !!this.hasta && this.hasta < this.desde;
  }

  // ---------- Carga ----------

  private filtrosActuales(page?: number): PedidoFiltros {
    return {
      num: this.busNumero.trim() || undefined,
      ci: this.busCedula.trim() || undefined,
      cliente: this.busCliente.trim() || undefined,
      estado_pedido: this.filtroEstadoPedido,
      estado_pago: this.filtroEstadoPago,
      tipo_entrega: this.filtroTipoEntrega,
      metodo_pago: this.filtroMetodoPago,
      desde: this.desde || undefined,
      hasta: this.hasta || undefined,
      retiro_vencido: this.filtroRetiroVencido || undefined,
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
        this.pedidos = res.data;
        this.currentPage = res.meta.current_page;
        this.lastPage = res.meta.last_page;
        this.perPage = res.meta.per_page;
        this.total = res.meta.total;
        this.rangoDesde = res.meta.desde;
        this.rangoHasta = res.meta.hasta;
        this.pagosPendientes = res.meta.pagos_pendientes;
        this.retirosVencidos = res.meta.retiros_vencidos;
        this.cargando = false;
      },
      error: (err) => {
        const mensaje =
          err?.error?.errors?.num?.[0] ??
          err?.error?.message ??
          'No se pudieron cargar los pedidos';
        this.toastr.error(mensaje, 'Error');
        this.cargando = false;
      },
    });
  }

  /** «Aplicar»: filtros + rango de fechas, combinados con la búsqueda vigente. */
  aplicar(): void {
    this.cargar(1);
  }

  limpiar(): void {
    this.busNumero = '';
    this.busCedula = '';
    this.busCliente = '';
    this.filtroEstadoPedido = null;
    this.filtroEstadoPago = null;
    this.filtroTipoEntrega = null;
    this.filtroMetodoPago = null;
    this.desde = '';
    this.hasta = '';
    this.filtroRetiroVencido = false;
    this.cargar(1);
  }

  /** Clic en la alerta: filtra por los pagos que esperan verificación. */
  verPagosPendientes(): void {
    const pendiente = this.estadosPago.find((e) => e.epa_codigo === 'PAGO_VERIFICAR');
    if (!pendiente) return;

    this.filtroRetiroVencido = false;
    this.filtroEstadoPago = pendiente.epa_id;
    this.filtrosVisibles = true;
    this.cargar(1);
  }

  /**
   * Clic en la alerta de retiros vencidos: estos pedidos ya tienen pago
   * confirmado (si no, el barrido ya los habría cancelado), así que no
   * hace falta combinar con ningún otro filtro de estado.
   */
  verRetirosVencidos(): void {
    this.filtroEstadoPago = null;
    this.filtroRetiroVencido = true;
    this.filtrosVisibles = true;
    this.cargar(1);
  }

  // ---------- Paginación ----------

  /** Ventana deslizante de 5 páginas para no romper el layout con 147 páginas. */
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

  // ---------- Exportación ----------

  exportarCsv(): void {
    if (this.exportando) return;
    this.exportando = true;

    this.service.exportar(this.filtrosActuales()).subscribe({
      next: (blob) => {
        this.descargar(blob, `pedidos_${new Date().toISOString().slice(0, 10)}.csv`);
        this.toastr.success('Exportación generada', 'Listo');
        this.exportando = false;
      },
      error: async (err) => {
        // El 422 llega como Blob porque la petición pidió responseType blob.
        let mensaje = 'No se pudo exportar';
        if (err?.error instanceof Blob) {
          try {
            mensaje = JSON.parse(await err.error.text())?.message ?? mensaje;
          } catch {
            /* se mantiene el mensaje genérico */
          }
        }
        this.toastr.error(mensaje, 'Error');
        this.exportando = false;
      },
    });
  }

  private descargar(blob: Blob, nombre: string): void {
    const url = window.URL.createObjectURL(blob);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = nombre;
    enlace.click();
    window.URL.revokeObjectURL(url);
  }

  // ---------- Navegación ----------
    verPedido(pedido: PedidoLista): void {
    this.router.navigate(['/pedidos', pedido.ped_id]);
    }

  // ---------- Presentación ----------

  colorEstadoPago(codigo: string | null): string {
    switch (codigo) {
      case 'CONFIRMADO': return 'success';
      case 'PARCIAL':    return 'info';
      case 'RECHAZADO':     return 'danger';
      case 'PAGO_VERIFICAR': return 'warning';
      default:           return 'secondary';
    }
  }

  colorEstadoPedido(codigo: string | null): string {
    switch (codigo) {
      case 'ENTREGADO':      return 'success';
      case 'LISTO':          return 'primary';
      case 'EN_PREPARACION': return 'info';
      case 'CANCELADO':      return 'danger';
      case 'PENDIENTE_PEDIDO': return 'warning';
      default:               return 'secondary';
    }
  }

  /**
   * Texto de la columna de retiro. Reutiliza Intl.RelativeTimeFormat para
   * no reinventar el cálculo de "hace X días" / "en X días" a mano.
   */
  private readonly formatoRelativo = new Intl.RelativeTimeFormat('es-PY', { numeric: 'auto' });

  textoRetiro(vence: string): string {
    const diffMs = new Date(vence).getTime() - Date.now();
    const dias = Math.round(diffMs / 86_400_000);

    return this.formatoRelativo.format(dias, 'day');
  }

  /** Guaraníes: separador de miles y sin decimales. */
  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}