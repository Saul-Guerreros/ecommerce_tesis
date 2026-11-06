import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { URL_BACKEND } from '../../../config/config';
import { ServicioCliente } from '../../../core/models/servicio-tecnico.model';
import { PagoServicioService } from '../../../core/services/pago-servicio.service';
import { FacturaServicioCliente } from '../../../core/models/factura-servicio.model';
import { FacturaServicioService } from '../../../core/services/factura-servicio.service';
import { ServicioTecnicoService } from '../../../core/services/servicio-tecnico.service';
import { PagoTarjeta, TarjetaPagosComponent } from '../../../shared/components/tarjeta-pagos/tarjeta-pagos.component';

/**
 * C4 - Detalle del servicio: timeline de 5 estados, datos y presupuesto
 * con Aprobar / Rechazar (Fase 4).
 */
@Component({
  selector: 'app-detalle-servicio',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, ReactiveFormsModule, RouterModule, TarjetaPagosComponent],
  templateUrl: './detalle-servicio.component.html',
  styleUrl: './detalle-servicio.component.css',
})
export class DetalleServicioComponent implements OnInit {
  readonly pasos = ['RECIBIDO', 'EN DIAGNÓSTICO', 'EN REPARACIÓN', 'LISTO', 'ENTREGADO'];

  servicio: ServicioCliente | null = null;
  cargando = true;
  noEncontrado = false;

  // Respuesta al presupuesto
  confirmando: 'aprobar' | 'rechazar' | null = null;
  respondiendo = false;
  motivo = new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.minLength(3), Validators.maxLength(255)] });

  // Pago del servicio
  abriendoId: number | null = null;
  subiendoId: number | null = null;

  // Factura del servicio (se emite al confirmarse el pago)
  factura: FacturaServicioCliente | null = null;
  descargandoFactura = false;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private service: ServicioTecnicoService,
    private pagos: PagoServicioService,
    private facturas: FacturaServicioService,
    private toastr: ToastrService,
  ) {}

  // ---------- Paso 6: constancias y garantía ----------

  descargarConstancia(tipo: 'recepcion' | 'retiro'): void {
    if (!this.servicio) return;
    const numero = this.servicio.sse_numero;
    this.service.constancia(numero, tipo).subscribe({
      next: (pdf) => {
        const url = URL.createObjectURL(pdf);
        const a = document.createElement('a');
        a.href = url;
        a.download = `constancia-${tipo}-${numero}.pdf`;
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 30_000);
      },
      error: () => this.toastr.error('No se pudo descargar la constancia', 'Servicio técnico'),
    });
  }

  reclamarGarantia(): void {
    if (!this.servicio) return;
    this.router.navigate(['/servicio-tecnico/solicitar'], { queryParams: { reclamo: this.servicio.sse_numero } });
  }

  ngOnInit(): void {
    this.cargar();
  }

  private cargar(): void {
    const numero = this.route.snapshot.paramMap.get('numero') ?? '';
    this.service.detalle(numero).subscribe({
      next: (res) => { this.servicio = res.data; this.cargando = false; this.cargarFactura(); },
      error: () => { this.noEncontrado = true; this.cargando = false; },
    });
  }

  // ---------- Factura ----------

  /** Solo si el servicio tiene cobro: la garantía no se factura. */
  private cargarFactura(): void {
    const s = this.servicio;
    if (!s?.pago || s.pago.total <= 0) { this.factura = null; return; }

    this.facturas.opciones(s.sse_numero).subscribe({
      next: (res) => (this.factura = res.data.factura ?? null),
      error: () => (this.factura = null),   // no bloquea el detalle
    });
  }

  descargarFactura(): void {
    if (!this.servicio || !this.factura?.disponible || this.descargandoFactura) return;

    const numero = this.factura.numero;
    this.descargandoFactura = true;
    this.facturas.kude(this.servicio.sse_numero).subscribe({
      next: (pdf) => {
        this.descargandoFactura = false;
        const url = URL.createObjectURL(pdf);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Factura_${numero}.pdf`;
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 30_000);
      },
      error: () => {
        this.descargandoFactura = false;
        this.toastr.error('No pudimos descargar tu factura.', 'Servicio técnico');
      },
    });
  }

  // ---------- Pago del servicio ----------

  /** Adapta los pagos del servicio a la forma genérica de app-tarjeta-pagos. */
  get pagosTarjeta(): PagoTarjeta[] {
    return (this.servicio?.pago?.pagos ?? []).map((p) => ({ ...p, id: p.spa_id }));
  }

  /** El endpoint exige el token: se baja como Blob y se abre en otra pestaña. */
  verComprobante(pago: PagoTarjeta): void {
    if (!pago.comprobante_url || this.abriendoId !== null) return;

    const url = pago.comprobante_url.startsWith('http')
      ? pago.comprobante_url
      : URL_BACKEND.replace(/\/$/, '') + '/' + pago.comprobante_url.replace(/^\//, '');

    this.abriendoId = pago.id;
    this.pagos.descargarArchivo(url).subscribe({
      next: (blob) => {
        const objeto = URL.createObjectURL(blob);
        window.open(objeto, '_blank');
        setTimeout(() => URL.revokeObjectURL(objeto), 60_000);
        this.abriendoId = null;
      },
      error: () => {
        this.abriendoId = null;
        this.toastr.error('No pudimos abrir el comprobante.', 'Servicio técnico');
      },
    });
  }

  reemplazarComprobante(evento: { pago: PagoTarjeta; archivo: File }, tarjeta: TarjetaPagosComponent): void {
    if (!this.servicio || this.subiendoId !== null) return;

    this.subiendoId = evento.pago.id;
    this.pagos.reemplazarComprobante(this.servicio.sse_numero, evento.pago.id, evento.archivo).subscribe({
      next: (resp) => {
        this.subiendoId = null;
        tarjeta.limpiar(evento.pago.id);
        this.toastr.success(resp.message, 'Servicio técnico');
        this.cargar();
      },
      error: (e) => {
        this.subiendoId = null;
        this.toastr.error(
          e.error?.errors?.comprobante?.[0] ?? e.error?.errors?.pago?.[0] ?? e.error?.message ?? 'No pudimos enviar el comprobante.',
          'Servicio técnico',
        );
      },
    });
  }

  /** La garantía de compra tiene su propio recorrido (gestión con la empresa). */
  get pasosServicio(): string[] {
    return this.servicio?.pasos ?? this.pasos;
  }

  estadoPaso(i: number): 'hecho' | 'actual' | 'pendiente' {
    const actual = this.servicio?.paso_actual ?? 0;
    if (i + 1 < actual) return 'hecho';
    if (i + 1 === actual) return 'actual';
    return 'pendiente';
  }

  // ---------- Presupuesto ----------

  get repuestos() {
    return this.servicio?.presupuesto?.lineas.filter((l) => l.tipo === 'REPUESTO') ?? [];
  }

  get manoDeObra() {
    return this.servicio?.presupuesto?.lineas.filter((l) => l.tipo === 'MANO_OBRA') ?? [];
  }

  pedirConfirmacion(accion: 'aprobar' | 'rechazar'): void {
    this.confirmando = accion;
    this.motivo.reset('');
  }

  responder(): void {
    if (!this.servicio || !this.confirmando) return;
    if (this.confirmando === 'rechazar' && this.motivo.invalid) {
      this.motivo.markAsTouched();
      return;
    }

    const numero = this.servicio.sse_numero;
    const peticion = this.confirmando === 'aprobar'
      ? this.service.aprobarPresupuesto(numero)
      : this.service.rechazarPresupuesto(numero, this.motivo.value);

    this.respondiendo = true;
    peticion.subscribe({
      next: (res) => {
        this.respondiendo = false;
        this.confirmando = null;
        this.servicio = res.data;
        this.toastr.success(res.message, 'Servicio técnico');
      },
      error: (err) => {
        this.respondiendo = false;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (errores?.['motivo']) { this.motivo.setErrors({ backend: errores['motivo'][0] }); return; }
        const msg = errores ? Object.values(errores)[0]?.[0] : null;
        this.toastr.error(msg ?? err?.error?.message ?? 'No se pudo registrar tu respuesta', 'Servicio técnico');
      },
    });
  }
}
