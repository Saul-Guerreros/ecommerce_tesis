import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import {
  CobroDetalle, DevolucionItem, DevolucionNota, MEDIOS_MOSTRADOR, MetodoPagoOpcion, RespuestaCobro,
} from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';

type Modo = 'acreditar' | 'devolver' | 'anular' | 'liberar';

/**
 * Devoluciones del servicio (guía Facturacion_API, cap. 30). Dos hechos:
 *   1. Nota de crédito: corrige el documento fiscal (anticipo o final).
 *   2. Devolución: el dinero que sale de la caja o la cuenta.
 * Además: liberar el anticipo de una final de neto cero.
 * Qué botones se ven lo decide el backend (puede_*).
 */
@Component({
  selector: 'app-devolucion-servicio',
  standalone: true,
  imports: [CommonModule, FormsModule, AlertModule, BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule],
  templateUrl: './devolucion-servicio.component.html',
})
export class DevolucionServicioComponent implements OnInit {
  @Input() detalle: CobroDetalle | null = null;
  @Output() guardado = new EventEmitter<CobroDetalle>();

  metodos: MetodoPagoOpcion[] = [];

  visible = false;
  modo: Modo = 'acreditar';
  nota: DevolucionNota | null = null;
  devolucion: DevolucionItem | null = null;
  guardando = false;
  errores: Record<string, string> = {};

  f = { monto: null as number | null, observacion: '', mpa_codigo: '', nro_transaccion: '', banco: '', fecha: '', motivo: '' };

  constructor(private service: CobrosServicioService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.service.config().subscribe({
      next: (res) => (this.metodos = res.data.metodos.filter((m) => MEDIOS_MOSTRADOR.includes(m.mpa_codigo))),
    });
  }

  get dev() { return this.detalle?.devolucion ?? null; }

  get titulo(): string {
    return ({
      acreditar: 'Nota de crédito del anticipo',
      devolver: `Registrar devolución — NC ${this.nota?.numero ?? ''}`,
      anular: 'Anular devolución',
      liberar: 'Liberar anticipo de la factura final',
    } as Record<Modo, string>)[this.modo];
  }

  get requiereReferencia(): boolean {
    return this.metodos.find((m) => m.mpa_codigo === this.f.mpa_codigo)?.mpa_requcom ?? false;
  }

  // ---------- Apertura ----------

  private abrir(modo: Modo): void {
    this.modo = modo;
    this.errores = {};
    this.f = { monto: null, observacion: '', mpa_codigo: '', nro_transaccion: '', banco: '', fecha: this.hoyLocal(), motivo: '' };
    this.visible = true;
  }

  abrirAcreditar(): void {
    this.abrir('acreditar');
    this.f.monto = this.dev?.disponible ?? null;
  }

  abrirDevolver(n: DevolucionNota): void {
    this.nota = n;
    this.abrir('devolver');
    this.f.monto = n.por_devolver;
  }

  abrirAnular(n: DevolucionNota, d: DevolucionItem): void {
    this.nota = n;
    this.devolucion = d;
    this.abrir('anular');
  }

  abrirLiberar(): void {
    this.abrir('liberar');
  }

  // ---------- Guardar ----------

  guardar(): void {
    if (!this.detalle || this.guardando || !this.validarLocal()) return;
    const id = this.detalle.sse_id;

    const accion$: Observable<RespuestaCobro> = ({
      acreditar: () => this.service.acreditarAnticipo(id, { monto: this.f.monto, observacion: this.f.observacion.trim() || null }),
      devolver: () => this.service.registrarDevolucion(id, this.nota!.ncv_id, {
        mpa_codigo: this.f.mpa_codigo, monto: this.f.monto,
        nro_transaccion: this.f.nro_transaccion.trim() || null, banco: this.f.banco.trim() || null,
        fecha: this.f.fecha || null, observacion: this.f.observacion.trim() || null,
      }),
      anular: () => this.service.anularDevolucion(id, this.devolucion!.dev_id, this.f.motivo.trim()),
      liberar: () => this.service.liberarAnticipo(id, this.f.motivo.trim()),
    } as Record<Modo, () => Observable<RespuestaCobro>>)[this.modo]();

    this.guardando = true;
    accion$.subscribe({
      next: (res) => {
        this.guardando = false;
        this.visible = false;
        this.toastr.success(res.message, 'Devoluciones');
        this.guardado.emit(res.data);
      },
      error: (err) => {
        this.guardando = false;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (err?.status === 422 && errores) {
          this.errores = Object.fromEntries(Object.entries(errores).map(([k, m]) => [this.campo(k), m[0]]));
          return;
        }
        this.errores = { general: err?.error?.message ?? 'No se pudo completar la operación' };
      },
    });
  }

  /** Errores sin campo propio (nota_credito, devolucion, anticipo) van al general. */
  private campo(clave: string): string {
    return ['monto', 'observacion', 'mpa_codigo', 'nro_transaccion', 'fecha', 'motivo'].includes(clave) ? clave : 'general';
  }

  private validarLocal(): boolean {
    this.errores = {};
    if (this.modo === 'acreditar' || this.modo === 'devolver') {
      if (!this.f.monto || this.f.monto < 1) this.errores['monto'] = 'Ingrese un monto mayor que cero.';
    }
    if (this.modo === 'devolver') {
      if (!this.f.mpa_codigo) this.errores['mpa_codigo'] = 'Seleccione cómo se devuelve el dinero.';
      if (this.requiereReferencia && !this.f.nro_transaccion.trim()) this.errores['nro_transaccion'] = 'La referencia es obligatoria.';
    }
    if ((this.modo === 'anular' || this.modo === 'liberar') && this.f.motivo.trim().length < 5) {
      this.errores['motivo'] = 'El motivo debe tener al menos 5 caracteres.';
    }
    return Object.keys(this.errores).length === 0;
  }

  // ---------- Vista ----------

  colorFe(estado?: string): string {
    return ({ APROBADA: 'success', PENDIENTE: 'warning', RECHAZADA: 'danger' } as Record<string, string>)[estado ?? ''] ?? 'secondary';
  }

  private hoyLocal(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  formatoGs(valor: number | null | undefined): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}