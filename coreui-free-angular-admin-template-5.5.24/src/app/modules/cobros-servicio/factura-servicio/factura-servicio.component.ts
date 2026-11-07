import { Component, EventEmitter, Input, OnDestroy, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { FacturaServicio, TipoFactura } from '../models/factura-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';

/**
 * Una factura del servicio (final o de anticipo): número, estado ante la
 * DNIT, anticipo aplicado/disponible y acciones (KuDE, XML, reintentar,
 * reenviar, WhatsApp asistido).
 *
 * Recibe la factura ya armada en el detalle de cobro (no hace su propia
 * petición). Tras reintentar o reenviar emite 'cambio' y el contenedor
 * vuelve a leer el detalle.
 */
@Component({
  selector: 'app-factura-servicio',
  standalone: true,
  imports: [CommonModule, FormsModule, AlertModule, BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './factura-servicio.component.html',
})
export class FacturaServicioComponent implements OnDestroy {
  @Input() solicitudId: number | null = null;
  @Input() tipo: TipoFactura = 'VENTA';
  @Input() factura: FacturaServicio | null = null;
  @Output() cambio = new EventEmitter<void>();

   procesando: 'kude' | 'xml' | 'reintentar' | 'reenviar' | 'anular' | null = null;
  reenvioVisible = false;
  destino = '';
  errorDestino: string | null = null;
  anularVisible = false;
  motivoAnular = '';
  errorAnular: string | null = null;

  private espera?: ReturnType<typeof setTimeout>;

  constructor(private service: CobrosServicioService, private toastr: ToastrService) {}

  ngOnDestroy(): void {
    clearTimeout(this.espera);
  }

  get esAnticipo(): boolean { return this.tipo === 'ANTICIPO'; }
  get etiqueta(): string { return this.esAnticipo ? 'Factura de anticipo' : 'Factura'; }

  colorEstado(estado?: string): string {
    return ({
      APROBADA: 'success', PREPARADA: 'info', PENDIENTE: 'warning',
      RECHAZADA: 'danger', RETIRADA: 'danger', CANCELADA: 'dark', INUTILIZADA: 'dark',
    } as Record<string, string>)[estado ?? ''] ?? 'secondary';
  }

    /** Color del estado de negocio (estado_detalle). */
  colorDetalle(codigo?: string): string {
    return ({
      COBRADA: 'success', COBRADO: 'success', APLICADO: 'success',
      PARCIAL: 'warning', APLICADO_PARCIAL: 'info',
      ACREDITADA_PARCIAL: 'info', ACREDITADO_PARCIAL: 'info',
      ACREDITADA_TOTAL: 'dark', ACREDITADO: 'dark', DEVUELTO: 'dark',
      ANULADA: 'secondary', ANULADO: 'secondary',
    } as Record<string, string>)[codigo ?? ''] ?? 'light';
  }

  formatoGs(valor: number | undefined | null): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }

  // ---------- Archivos ----------

  verKude(): void {
    if (!this.solicitudId || this.procesando) return;
    this.procesando = 'kude';
    this.service.kudeFactura(this.solicitudId, this.tipo).subscribe({
      next: (blob) => { this.procesando = null; this.service.abrirBlob(blob); },
      error: () => { this.procesando = null; this.toastr.error('El KuDE no está disponible', 'Error'); },
    });
  }

  descargarXml(): void {
    if (!this.solicitudId || !this.factura || this.procesando) return;
    this.procesando = 'xml';
    const prefijo = this.esAnticipo ? 'Anticipo' : 'Factura';
    this.service.xmlFactura(this.solicitudId, this.tipo).subscribe({
      next: (blob) => { this.procesando = null; this.service.descargarBlob(blob, `${prefijo}_${this.factura!.numero}.xml`); },
      error: () => { this.procesando = null; this.toastr.error('El XML no está disponible', 'Error'); },
    });
  }

  // ---------- Acciones ----------

  /** Solo encola el job: el estado se actualiza unos segundos después. */
  reintentar(): void {
    if (!this.solicitudId || this.procesando) return;
    this.procesando = 'reintentar';
    this.service.reintentarFactura(this.solicitudId, this.tipo).subscribe({
      next: (res) => {
        this.procesando = null;
        this.toastr.info(res.message, this.etiqueta);
        clearTimeout(this.espera);
        this.espera = setTimeout(() => this.cambio.emit(), 5000);
      },
      error: (err) => {
        this.procesando = null;
        this.toastr.error(CobrosServicioService.mensajeError(err, 'No se pudo reintentar'), 'Error');
      },
    });
  }

  actualizar(): void {
    this.cambio.emit();
  }

  abrirReenvio(): void {
    this.destino = this.factura?.email ?? '';
    this.errorDestino = null;
    this.reenvioVisible = true;
  }

  reenviar(): void {
    if (!this.solicitudId || this.procesando) return;
    const destino = this.destino.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(destino)) { this.errorDestino = 'Ingrese un correo válido.'; return; }

    this.procesando = 'reenviar';
    this.service.reenviarFactura(this.solicitudId, { canal: 'CORREO', destino }, this.tipo).subscribe({
      next: (res) => {
        this.procesando = null;
        this.reenvioVisible = false;
        this.factura = res.data;
        this.toastr.success(res.message, `${this.etiqueta} enviada`);
        this.cambio.emit();
      },
      error: (err) => {
        this.procesando = null;
        if (err?.status === 422 && err?.error?.errors) { this.errorDestino = CobrosServicioService.mensajeError(err, ''); return; }
        this.toastr.error(err?.error?.message ?? 'No se pudo reenviar la factura', 'Error');
      },
    });
  }

    abrirAnular(): void {
    this.motivoAnular = '';
    this.errorAnular = null;
    this.anularVisible = true;
  }

  anular(): void {
    if (!this.solicitudId || this.procesando) return;
    const motivo = this.motivoAnular.trim();
    if (motivo.length < 5) { this.errorAnular = 'El motivo debe tener al menos 5 caracteres.'; return; }

    this.procesando = 'anular';
    this.service.anularFactura(this.solicitudId, motivo, this.tipo).subscribe({
      next: (res) => {
        this.procesando = null;
        this.anularVisible = false;
        this.toastr.success(res.message, this.etiqueta);
        this.cambio.emit();
      },
      error: (err) => {
        this.procesando = null;
        this.errorAnular = CobrosServicioService.mensajeError(err, 'No se pudo anular la factura');
      },
    });
  }

  // ---------- WhatsApp asistido ----------

  /** Celular paraguayo en formato internacional sin "+": 0981123456 -> 595981123456. */
  private normalizarCelularPy(texto: string | null): string | null {
    let d = (texto ?? '').replace(/\D/g, '');
    if (d.startsWith('595')) d = d.slice(3);
    if (d.startsWith('0')) d = d.slice(1);
    return /^9\d{8}$/.test(d) ? `595${d}` : null;
  }

  get whatsappUrl(): string | null {
    const f = this.factura;
    const numero = this.normalizarCelularPy(f?.telefono ?? null);
    if (!f || !numero) return null;

    const nombre = f.tipo_doc === 'CI' ? f.razon_social.trim().split(/\s+/)[0] : f.razon_social.trim();
    const que = this.esAnticipo ? 'la factura del anticipo' : 'la factura';
    const consulta = f.fe?.qr ? ` Podés verificarla en: ${f.fe.qr}` : '';
    const texto = `Hola ${nombre}, te enviamos ${que} N.º ${f.numero} de tu servicio técnico.${consulta} ¡Gracias por confiar en nosotros!`;

    return `https://wa.me/${numero}?text=${encodeURIComponent(texto)}`;
  }

  abrirWhatsapp(): void {
    const url = this.whatsappUrl;
    if (url) window.open(url, '_blank', 'noopener');
  }
}