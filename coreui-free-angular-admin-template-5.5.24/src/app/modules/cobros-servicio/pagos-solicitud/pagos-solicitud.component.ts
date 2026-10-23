import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, SpinnerModule, TableModule } from '@coreui/angular';
import { CobroDetalle, PagoServicio } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';
import { ConfirmarPagoServicioComponent } from '../confirmar-pago-servicio/confirmar-pago-servicio.component';
import { RechazarPagoServicioComponent } from '../rechazar-pago-servicio/rechazar-pago-servicio.component';
import { RegistrarCobroComponent } from '../registrar-cobro/registrar-cobro.component';

/**
 * Sección "Pagos" de una solicitud: resumen de montos, pagos registrados y
 * acciones (verificar un pago web, cobrar un "al retirar", registrar un
 * cobro o anticipo). Autocontenida: la usan el detalle de la solicitud y la
 * bandeja "Cobros de servicios".
 *
 * Qué botones se ven lo decide el backend en 'acciones' (permiso, sucursal
 * y si la solicitud admite pagos); acá solo se cruza con el estado del pago.
 */
@Component({
  selector: 'app-pagos-solicitud',
  standalone: true,
  imports: [
    CommonModule, BadgeModule, ButtonModule, SpinnerModule, TableModule,
    ConfirmarPagoServicioComponent, RechazarPagoServicioComponent, RegistrarCobroComponent,
  ],
  templateUrl: './pagos-solicitud.component.html',
})
export class PagosSolicitudComponent implements OnChanges {
  @Input() solicitudId: number | null = null;
  /** Se registró algo: el contenedor refresca su propia vista (lista, historial). */
  @Output() actualizado = new EventEmitter<void>();

  detalle: CobroDetalle | null = null;
  cargando = false;
  procesandoId: number | null = null;

  seleccionado: PagoServicio | null = null;
  confirmarVisible = false;
  rechazarVisible = false;
  cobroVisible = false;

  constructor(private service: CobrosServicioService, private toastr: ToastrService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['solicitudId'] && this.solicitudId) this.recargar();
  }

  recargar(): void {
    if (!this.solicitudId) return;
    this.cargando = true;
    this.service.detalle(this.solicitudId).subscribe({
      next: (res) => { this.detalle = res.data; this.cargando = false; },
      error: () => { this.cargando = false; this.toastr.error('No se pudieron cargar los pagos', 'Error'); },
    });
  }

  // ---------- Reglas de botones ----------

  enVerificacion(p: PagoServicio): boolean { return p.estado.codigo === 'PAGO_VERIFICAR'; }
  esEfectivo(p: PagoServicio): boolean { return p.metodo.codigo === 'CONTRAENTREGA'; }

  puedeVerificar(p: PagoServicio): boolean {
    return !!this.detalle?.acciones.verificar && this.enVerificacion(p) && !this.esEfectivo(p);
  }

  /** "Pagar al retirar": lo confirma quien cobra, no el verificador. */
  puedeCobrarAlRetirar(p: PagoServicio): boolean {
    return !!this.detalle?.acciones.cobrar && this.enVerificacion(p) && this.esEfectivo(p);
  }

  /** Rechazar un "al retirar" (el cliente paga de otra forma): libera el monto. */
  puedeRechazarEfectivo(p: PagoServicio): boolean {
    return !!this.detalle?.acciones.verificar && this.enVerificacion(p) && this.esEfectivo(p);
  }

  get puedeRegistrarCobro(): boolean {
    const r = this.detalle?.resumen;
    if (!this.detalle?.acciones.cobrar || !r) return false;
    return r.consolidado ? r.saldo > 0 : true;   // antes de LISTA: anticipo
  }

  colorEstado(codigo: string | null): string {
    return ({ PAGO_VERIFICAR: 'primary', CONFIRMADO: 'success', RECHAZADO: 'danger', PARCIAL: 'info', PENDIENTE_PAGO: 'warning' } as Record<string, string>)[codigo ?? ''] ?? 'secondary';
  }

  // ---------- Acciones ----------

  abrir(modal: 'confirmar' | 'rechazar', p: PagoServicio): void {
    this.seleccionado = p;
    this.confirmarVisible = modal === 'confirmar';
    this.rechazarVisible = modal === 'rechazar';
  }

  verComprobante(p: PagoServicio): void {
    if (!this.detalle || this.procesandoId !== null) return;
    this.procesandoId = p.spa_id;
    this.service.comprobante(this.detalle.sse_id, p.spa_id).subscribe({
      next: (blob) => { this.procesandoId = null; this.service.abrirBlob(blob); },
      error: () => { this.procesandoId = null; this.toastr.error('No se pudo abrir el comprobante', 'Error'); },
    });
  }

  cobrarAlRetirar(p: PagoServicio): void {
    if (!this.detalle || this.procesandoId !== null) return;
    this.procesandoId = p.spa_id;
    this.service.cobrarAlRetirar(this.detalle.sse_id, p.spa_id).subscribe({
      next: (res) => { this.procesandoId = null; this.toastr.success(res.message, 'Cobro registrado'); this.onGuardado(res.data); },
      error: (err) => {
        this.procesandoId = null;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        this.toastr.error(errores ? Object.values(errores)[0][0] : err?.error?.message ?? 'No se pudo registrar el cobro', 'Error');
      },
    });
  }

  onGuardado(detalle: CobroDetalle): void {
    this.detalle = detalle;
    this.actualizado.emit();
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}
