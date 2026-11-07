import { Component, EventEmitter, Input, OnChanges, OnDestroy, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { CobroDetalle, PagoServicio } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';
import { ValidarComprobanteServicioComponent } from '../validar-comprobante-servicio/validar-comprobante-servicio.component';
import { RechazarPagoServicioComponent } from '../rechazar-pago-servicio/rechazar-pago-servicio.component';
import { RegistrarCobroComponent } from '../registrar-cobro/registrar-cobro.component';
import { FacturaServicioComponent } from '../factura-servicio/factura-servicio.component';
import { GenerarFacturaServicioComponent, ModoFactura } from '../generar-factura-servicio/generar-factura-servicio.component';
import { DevolucionServicioComponent } from '../devolucion-servicio/devolucion-servicio.component';


/**
 * Sección "Pagos" de una solicitud. Ciclo comprobante -> factura -> cobro
 * (igual que el pedido):
 *
 *   Anticipo   [Registrar anticipo] (mostrador) o pago web -> [Validar] ->
 *              [Generar factura de anticipo] -> [Cobrar]
 *   Final      LISTA -> [Generar factura] (aplica el anticipo) -> [Cobrar]
 *   Devolución nota de crédito + devolución del dinero
 *
 * Los botones los habilita el backend (acciones.*, pagos[].puede_*). Una
 * factura recién emitida tarda unos segundos en tener su documento: la
 * sección se relee sola mientras tanto.
 */
@Component({
  selector: 'app-pagos-solicitud',
  standalone: true,
  imports: [
    CommonModule, FormsModule, BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule,
    ValidarComprobanteServicioComponent, RechazarPagoServicioComponent, RegistrarCobroComponent,
    FacturaServicioComponent, GenerarFacturaServicioComponent, DevolucionServicioComponent,
  ],
  templateUrl: './pagos-solicitud.component.html',
})
export class PagosSolicitudComponent implements OnChanges, OnDestroy {
  @Input() solicitudId: number | null = null;
  /** El contenedor lo cambia en cada apertura: fuerza releer sin destruir el componente. */
  @Input() refresco = 0;
  /** Se registró algo: el contenedor refresca su propia vista (lista, historial). */
  @Output() actualizado = new EventEmitter<void>();

  detalle: CobroDetalle | null = null;
  cargando = false;
  procesandoId: number | null = null;

  seleccionado: PagoServicio | null = null;
  validarVisible = false;
  rechazarVisible = false;
  cobroVisible = false;
  generarVisible = false;
  modoGenerar: ModoFactura = 'final';
    // Anular cobro
  anularVisible = false;
  cobroAnular: PagoServicio | null = null;
  motivoAnular = '';
  errorAnular: string | null = null;
  anulando = false;
  verAnuladas = false;
  private espera?: ReturnType<typeof setTimeout>;
  private intentosEspera = 0;

  constructor(private service: CobrosServicioService, private toastr: ToastrService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['solicitudId'] || changes['refresco']) && this.solicitudId) {
      this.intentosEspera = 0;
      this.recargar();
    }
  }

  ngOnDestroy(): void {
    clearTimeout(this.espera);
  }

  recargar(): void {
    if (!this.solicitudId) return;
    this.cargando = true;
    this.service.detalle(this.solicitudId).subscribe({
      next: (res) => { this.cargando = false; this.aplicar(res.data); },
      error: () => { this.cargando = false; this.toastr.error('No se pudieron cargar los pagos', 'Error'); },
    });
  }

  private aplicar(d: CobroDetalle): void {
    this.detalle = d;
    this.programarEspera();
  }

  /** Factura emitida sin documento todavía (job en curso): se relee cada 5 s, hasta 1 minuto. */
  private programarEspera(): void {
    clearTimeout(this.espera);
    const f = this.detalle?.facturas;
    const esperando = [f?.anticipo, f?.final].some((x) => x?.fe && !x.fe.documento && x.fe.estado === 'PENDIENTE');

    if (esperando && this.intentosEspera < 12) {
      this.intentosEspera++;
      this.espera = setTimeout(() => this.recargar(), 5000);
    }
  }

  // ---------- Vista ----------

  /** El pago está facturado y pendiente, pero su factura todavía no tiene documento. */
  esperandoDocumento(p: PagoServicio): boolean {
    if (p.estado.codigo !== 'PAGO_VERIFICAR' || !p.facturado || p.puede_cobrar) return false;
    const f = p.tipo === 'ANTICIPO' ? this.detalle?.facturas.anticipo : this.detalle?.facturas.final;
    return !!f?.fe && !f.fe.documento;
  }

  colorEstado(codigo: string | null): string {
    return ({ PAGO_VERIFICAR: 'primary', CONFIRMADO: 'success', RECHAZADO: 'danger', PARCIAL: 'info', PENDIENTE_PAGO: 'warning' } as Record<string, string>)[codigo ?? ''] ?? 'secondary';
  }

  etiquetaEstado(p: PagoServicio): string {
    if (p.estado.codigo === 'CONFIRMADO') return 'Cobrado';
    if (p.estado.codigo === 'PAGO_VERIFICAR') return p.facturado ? 'Por cobrar' : 'Declarado';
    return p.estado.descripcion ?? '';
  }

  // ---------- Acciones ----------

  abrir(modal: 'validar' | 'rechazar' | 'cobrar', p: PagoServicio): void {
    this.seleccionado = p;
    this.validarVisible = modal === 'validar';
    this.rechazarVisible = modal === 'rechazar';
    this.cobroVisible = modal === 'cobrar';
  }

  abrirGenerar(modo: ModoFactura): void {
    this.modoGenerar = modo;
    this.generarVisible = true;
  }

  verComprobante(p: PagoServicio): void {
    if (!this.detalle || this.procesandoId !== null) return;
    this.procesandoId = p.spa_id;
    this.service.comprobante(this.detalle.sse_id, p.spa_id).subscribe({
      next: (blob) => { this.procesandoId = null; this.service.abrirBlob(blob); },
      error: () => { this.procesandoId = null; this.toastr.error('No se pudo abrir el comprobante', 'Error'); },
    });
  }

    abrirAnularCobro(p: PagoServicio): void {
    this.cobroAnular = p;
    this.motivoAnular = '';
    this.errorAnular = null;
    this.anularVisible = true;
  }

  anularCobro(): void {
    if (!this.detalle || !this.cobroAnular?.cobro || this.anulando) return;
    const motivo = this.motivoAnular.trim();
    if (motivo.length < 5) { this.errorAnular = 'El motivo debe tener al menos 5 caracteres.'; return; }

    this.anulando = true;
    this.service.anularCobro(this.detalle.sse_id, this.cobroAnular.cobro.cob_id, motivo).subscribe({
      next: (res) => {
        this.anulando = false;
        this.anularVisible = false;
        this.toastr.success(res.message, 'Cobro anulado');
        this.onGuardado(res.data);
      },
      error: (err) => {
        this.anulando = false;
        this.errorAnular = CobrosServicioService.mensajeError(err, 'No se pudo anular el cobro');
      },
    });
  }
  onGuardado(detalle: CobroDetalle): void {
    this.intentosEspera = 0;
    this.aplicar(detalle);
    this.actualizado.emit();
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}