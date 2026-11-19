import { CommonModule, CurrencyPipe, DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  MedioPagoCuota, MiCuota, MisCuotasRespuesta,
} from '../../../core/models/cuota-cliente.model';
import { MisCuotasService } from '../../../core/services/mis-cuotas.service';
import { PedidoClienteService } from '../../../core/services/pedido-cliente.service';

/** Mismo límite y formatos que el comprobante del Paso 3 del checkout. */
const TIPOS_COMPROBANTE = ['image/jpeg', 'image/png', 'application/pdf'];
const MAX_COMPROBANTE_BYTES = 5 * 1024 * 1024;

/**
 * Sección "Mis Cuotas" de una compra a crédito.
 *
 * Selección
 * ---------
 * Las cuotas se pagan EN ORDEN, sin saltear (lo valida también
 * CuotaPagoService). Por eso la selección es contigua desde la pendiente
 * más antigua: sólo se puede marcar la siguiente de una ya marcada, y al
 * desmarcar una se cae todo lo que venía después.
 *
 * El clic sobre el registro selecciona esa única cuota (y sólo funciona
 * sobre la primera pendiente). La selección múltiple es exclusiva de la
 * casilla.
 *
 * Pago
 * ----
 * Esta pantalla no cobra: confirma y deriva. [Pagar] abre el modal y, al
 * continuar, navega a /mis-cuotas/:numero/pagar con las cuotas elegidas
 * en la query. Esa página es la que declara el pago.
 */
@Component({
  selector: 'app-cuota-detalle',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, DatePipe, RouterModule],
  templateUrl: './cuota-detalle.component.html',
  styleUrl: './cuota-detalle.component.css',
})
export class CuotaDetalleComponent implements OnInit {
  numero = '';
  datos: MisCuotasRespuesta | null = null;
  cargando = true;

  /** cco_id de las cuotas marcadas. */
  seleccion = new Set<number>();

  /** Modal de confirmación previo al pago. */
  modalAbierto = false;

  /** Reenvío de un comprobante rechazado: medio que se está corrigiendo. */
  reenviandoMedioId: number | null = null;
  archivo: File | null = null;
  errorArchivo = '';
  enviando = false;

  abriendoId: number | null = null;
  abriendoFactura = false;

  constructor(
    private service: MisCuotasService,
    private pedidos: PedidoClienteService,
    private route: ActivatedRoute,
    private router: Router,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.numero = this.route.snapshot.paramMap.get('numero') ?? '';
    this.cargar();
  }

  private cargar(): void {
    this.cargando = true;

    this.service.getCuotas(this.numero).subscribe({
      next: (res) => {
        this.datos = res;
        this.cargando = false;
        this.seleccion.clear();
        this.cerrarReenvio();
      },
      error: (e) => {
        this.cargando = false;
        this.toastr.error(e.error?.message ?? 'No pudimos cargar tus cuotas.', 'Error');
        this.router.navigate(['/mis-cuotas'], { replaceUrl: true });
      },
    });
  }

  // ---------- Selección ----------

  get pendientes(): MiCuota[] {
    return (this.datos?.cuotas ?? []).filter((c) => c.puede_pagar);
  }

  get elegidas(): MiCuota[] {
    return (this.datos?.cuotas ?? []).filter((c) => this.seleccion.has(c.cco_id));
  }

  get cantidadSeleccionada(): number {
    return this.seleccion.size;
  }

  get totalSeleccionado(): number {
    return this.elegidas.reduce((suma, c) => suma + c.importe, 0);
  }

  /** Texto del modal: "N° 1 y 2", "N° 3". */
  get numerosElegidos(): string {
    const numeros = this.elegidas.map((c) => c.numero);

    if (numeros.length <= 1) return `N° ${numeros[0] ?? ''}`;

    return `N° ${numeros.slice(0, -1).join(', ')} y ${numeros[numeros.length - 1]}`;
  }

  esSiguiente(c: MiCuota): boolean {
    return this.pendientes[0]?.cco_id === c.cco_id;
  }

  estaSeleccionada(c: MiCuota): boolean {
    return this.seleccion.has(c.cco_id);
  }

  puedeMarcar(c: MiCuota): boolean {
    if (!c.puede_pagar) return false;

    const i = this.pendientes.findIndex((p) => p.cco_id === c.cco_id);
    if (i < 0) return false;
    if (i === 0) return true;

    return this.seleccion.has(this.pendientes[i - 1].cco_id);
  }

  casillaHabilitada(c: MiCuota): boolean {
    return this.estaSeleccionada(c) || this.puedeMarcar(c);
  }

  alternar(c: MiCuota, evento?: Event): void {
    evento?.stopPropagation();

    if (this.estaSeleccionada(c)) {
      const i = this.pendientes.findIndex((p) => p.cco_id === c.cco_id);
      this.pendientes.slice(i).forEach((p) => this.seleccion.delete(p.cco_id));
      return;
    }

    if (this.puedeMarcar(c)) {
      this.seleccion.add(c.cco_id);
    }
  }

  seleccionarSolo(c: MiCuota): void {
    if (!this.esSiguiente(c)) return;

    this.seleccion.clear();
    this.seleccion.add(c.cco_id);
  }

  puedePagarFila(c: MiCuota): boolean {
    return this.esSiguiente(c) && this.seleccion.size <= 1;
  }

  get puedePagarSeleccionadas(): boolean {
    return this.seleccion.size > 1;
  }

  // ---------- Modal y navegación al pago ----------

  pagarFila(c: MiCuota, evento: Event): void {
    evento.stopPropagation();

    // El [Pagar] automarca su casilla.
    this.seleccion.clear();
    this.seleccion.add(c.cco_id);

    this.modalAbierto = true;
  }

  pagarSeleccionadas(): void {
    if (!this.puedePagarSeleccionadas) return;
    this.modalAbierto = true;
  }

  cancelarModal(): void {
    this.modalAbierto = false;
  }

  /**
   * Las cuotas viajan en la query y no en el estado del router: así el
   * link de la pantalla de pago sigue siendo válido al recargar.
   */
  continuarAlPago(): void {
    const ids = this.elegidas.map((c) => c.cco_id).join(',');

    this.modalAbierto = false;
    this.router.navigate(['/mis-cuotas', this.numero, 'pagar'], { queryParams: { cuotas: ids } });
  }

  // ---------- Reenvío de un comprobante rechazado ----------

  /** Medios de la declaración de esta cuota, para la columna Comprobante. */
  medios(c: MiCuota): MedioPagoCuota[] {
    return c.pago?.medios ?? [];
  }

  /** Motivos de rechazo de la cuota, para la alerta de abajo. */
  rechazos(c: MiCuota): MedioPagoCuota[] {
    return this.medios(c).filter((m) => m.motivo_rechazo !== null);
  }

  abrirReenvio(medio: MedioPagoCuota, evento: Event): void {
    evento.stopPropagation();

    this.reenviandoMedioId = medio.cpm_id;
    this.archivo = null;
    this.errorArchivo = '';
  }

  cerrarReenvio(): void {
    this.reenviandoMedioId = null;
    this.archivo = null;
    this.errorArchivo = '';
  }

  onArchivo(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    this.errorArchivo = '';

    if (file && !TIPOS_COMPROBANTE.includes(file.type)) {
      this.errorArchivo = 'El comprobante debe ser JPG, PNG o PDF.';
      input.value = '';
      this.archivo = null;
      return;
    }

    if (file && file.size > MAX_COMPROBANTE_BYTES) {
      this.errorArchivo = 'El comprobante no puede superar los 5 MB.';
      input.value = '';
      this.archivo = null;
      return;
    }

    this.archivo = file;
  }

  enviarReenvio(): void {
    if (this.enviando || this.reenviandoMedioId === null) return;

    if (!this.archivo) {
      this.errorArchivo = 'Adjuntá el comprobante de pago.';
      return;
    }

    this.enviando = true;

    this.service.reemplazarComprobante(this.reenviandoMedioId, this.archivo).subscribe({
      next: (res) => {
        this.enviando = false;
        this.toastr.success(res?.message ?? 'Comprobante enviado.', 'Listo');
        this.cargar();
      },
      error: (e) => {
        this.enviando = false;
        this.toastr.error(
          e.error?.errors?.comprobante?.[0] ?? e.error?.message ?? 'No pudimos enviar el comprobante.',
          'Error',
        );
      },
    });
  }

  // ---------- Estado visual ----------

  claseEstado(c: MiCuota): string {
    if (c.estado === 'COBRADA') return 'uk-label-success';
    if (c.estado === 'ANULADA') return 'uk-label';
    if (c.pago?.estado === 'PAGO_VERIFICAR') return 'uk-label-primary';
    if (c.pago?.estado === 'PENDIENTE_PAGO') return 'uk-label-primary';
    if (c.pago?.estado === 'RECHAZADO') return 'uk-label-danger';
    return c.vencida ? 'uk-label-danger' : 'uk-label-warning';
  }

  etiquetaEstado(c: MiCuota): string {
    if (c.estado === 'COBRADA') return 'Pagada';
    if (c.estado === 'ANULADA') return 'Anulada';
    if (c.pago?.estado === 'PAGO_VERIFICAR') return 'En verificación';
    // El efectivo y el cheque no tienen nada que verificar todavía: lo
    // que falta es que el cliente se acerque a la sucursal.
    if (c.pago?.estado === 'PENDIENTE_PAGO') return 'Pago a abonar';
    if (c.pago?.estado === 'RECHAZADO') return 'Rechazado';
    return c.vencida ? 'Vencida' : 'Pendiente';
  }

  // ---------- Archivos protegidos ----------

  verComprobante(medio: MedioPagoCuota, evento: Event): void {
    evento.stopPropagation();
    if (!medio.tiene_comprobante || this.abriendoId !== null) return;

    this.abriendoId = medio.cpm_id;

    this.pedidos.descargarArchivo(this.service.urlComprobante(medio.cpm_id)).subscribe({
      next: (blob) => {
        this.abrirBlob(blob);
        this.abriendoId = null;
      },
      error: () => {
        this.abriendoId = null;
        this.toastr.error('No pudimos abrir el comprobante.', 'Error');
      },
    });
  }

  verFactura(): void {
    if (this.abriendoFactura) return;

    this.abriendoFactura = true;

    this.pedidos.descargarArchivo(this.service.urlFactura(this.numero)).subscribe({
      next: (blob) => {
        this.abrirBlob(blob);
        this.abriendoFactura = false;
      },
      error: () => {
        this.abriendoFactura = false;
        this.toastr.error('No pudimos abrir la factura.', 'Error');
      },
    });
  }

  private abrirBlob(blob: Blob): void {
    const objeto = URL.createObjectURL(blob);
    window.open(objeto, '_blank');
    setTimeout(() => URL.revokeObjectURL(objeto), 60_000);
  }
}