import { Component, Input, OnChanges, OnDestroy, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { FacturaServicio } from '../models/factura-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';

/**
 * Sección "Factura" de una solicitud: número, cliente, estado ante la DNIT
 * (SIFEN) y acciones (KuDE, XML, reintentar, reenviar por correo y WhatsApp
 * asistido). Equivale a lo que el detalle del pedido muestra de su factura.
 *
 * La factura la emite el backend al quedar CONFIRMADO el cobro: acá no se
 * emite nada. 'refresco' cambia cada vez que la sección Pagos registra algo.
 * Qué acciones se ven lo decide el backend (acciones.reintentar / reenviar).
 */
@Component({
  selector: 'app-factura-servicio',
  standalone: true,
  imports: [CommonModule, FormsModule, AlertModule, BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './factura-servicio.component.html',
})
export class FacturaServicioComponent implements OnChanges, OnDestroy {
  @Input() solicitudId: number | null = null;
  @Input() refresco = 0;

  factura: FacturaServicio | null = null;
  cargando = false;
  procesando: 'kude' | 'xml' | 'reintentar' | 'reenviar' | null = null;

  reenvioVisible = false;
  destino = '';
  errorDestino: string | null = null;

  private espera?: ReturnType<typeof setTimeout>;

  constructor(private service: CobrosServicioService, private toastr: ToastrService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['solicitudId'] || changes['refresco']) && this.solicitudId) this.cargar();
  }

  ngOnDestroy(): void {
    clearTimeout(this.espera);
  }

  cargar(): void {
    if (!this.solicitudId) return;
    this.cargando = true;
    this.service.factura(this.solicitudId).subscribe({
      next: (res) => { this.factura = res.data; this.cargando = false; },
      error: () => { this.cargando = false; this.toastr.error('No se pudo cargar la factura', 'Error'); },
    });
  }

  // ---------- Vista ----------

  colorEstado(estado?: string): string {
    return ({ APROBADA: 'success', PENDIENTE: 'warning', RECHAZADA: 'danger' } as Record<string, string>)[estado ?? ''] ?? 'secondary';
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }

  // ---------- Archivos ----------

  verKude(): void {
    if (!this.solicitudId || this.procesando) return;
    this.procesando = 'kude';
    this.service.kudeFactura(this.solicitudId).subscribe({
      next: (blob) => { this.procesando = null; this.service.abrirBlob(blob); },
      error: () => { this.procesando = null; this.toastr.error('El KuDE no está disponible', 'Error'); },
    });
  }

  descargarXml(): void {
    if (!this.solicitudId || !this.factura || this.procesando) return;
    this.procesando = 'xml';
    this.service.xmlFactura(this.solicitudId).subscribe({
      next: (blob) => { this.procesando = null; this.service.descargarBlob(blob, `Factura_${this.factura!.numero}.xml`); },
      error: () => { this.procesando = null; this.toastr.error('El XML no está disponible', 'Error'); },
    });
  }

  // ---------- Acciones ----------

  /** Solo encola el job: el estado se actualiza unos segundos después. */
  reintentar(): void {
    if (!this.solicitudId || this.procesando) return;
    this.procesando = 'reintentar';
    this.service.reintentarFactura(this.solicitudId).subscribe({
      next: (res) => {
        this.procesando = null;
        this.toastr.info(res.message, 'Factura');
        clearTimeout(this.espera);
        this.espera = setTimeout(() => this.cargar(), 5000);
      },
      error: (err) => {
        this.procesando = null;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        this.toastr.error(errores ? Object.values(errores)[0][0] : err?.error?.message ?? 'No se pudo reintentar', 'Error');
      },
    });
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
    this.service.reenviarFactura(this.solicitudId, { canal: 'CORREO', destino }).subscribe({
      next: (res) => {
        this.procesando = null;
        this.reenvioVisible = false;
        this.factura = res.data;
        this.toastr.success(res.message, 'Factura enviada');
      },
      error: (err) => {
        this.procesando = null;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (err?.status === 422 && errores) { this.errorDestino = Object.values(errores)[0][0]; return; }
        this.toastr.error(err?.error?.message ?? 'No se pudo reenviar la factura', 'Error');
      },
    });
  }

  // ---------- WhatsApp asistido (mismo criterio que el pedido) ----------

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
    const consulta = f.fe?.qr ? ` Podés verificarla en: ${f.fe.qr}` : '';
    const texto = `Hola ${nombre}, te enviamos la factura N.º ${f.numero} de tu servicio técnico.${consulta} ¡Gracias por confiar en nosotros!`;

    return `https://wa.me/${numero}?text=${encodeURIComponent(texto)}`;
  }

  /** Abre el chat; el KuDE se adjunta a mano (se descarga con "Ver KuDE"). */
  abrirWhatsapp(): void {
    const url = this.whatsappUrl;
    if (url) window.open(url, '_blank', 'noopener');
  }
}
