import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  DatosBilletera, DatosTransferencia, MetodoPagoCodigo, MetodoPagoOpcion,
  ModoPago, PagoDividRequest, RegistrarPagoRequest,
} from '../../../core/models/checkout.model';
import { ResumenPagoServicio } from '../../../core/models/servicio-tecnico.model';
import { PagoServicioService } from '../../../core/services/pago-servicio.service';
import { PagoDivididoComponent } from '../../checkout/pago-dividido/pago-dividido.component';

/**
 * C4 - Pagar el saldo del servicio técnico (/mis-servicios/:numero/pago).
 *
 * Imita el Paso 3 del checkout (paso-pago) sin tocarlo: mismas tarjetas de
 * modo y método, mismos estilos (se reutiliza su CSS) y el mismo
 * app-pago-dividido, que recibe el SALDO como total. Diferencias:
 *   - No hay plazo ni reserva: el equipo queda en el taller.
 *   - El efectivo es "pagar al retirar": se confirma en el mostrador.
 *   - Sin tarjetas de productos/facturación: la facturación es otra etapa.
 */
@Component({
  selector: 'app-pago-servicio',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, RouterModule, PagoDivididoComponent],
  templateUrl: './pago-servicio.component.html',
  styleUrls: ['../../checkout/paso-pago/paso-pago.component.css', './pago-servicio.component.css'],
})
export class PagoServicioComponent implements OnInit {
  numero = '';
  resumen!: ResumenPagoServicio;

  metodos: MetodoPagoOpcion[] = [];
  datosTransferencia!: DatosTransferencia;
  datosBilletera!: DatosBilletera;
  mensajeEfectivo: string | null = null;

  modoPago: ModoPago | null = null;
  metodoSeleccionado: MetodoPagoCodigo | null = null;
  comprobanteArchivo: File | null = null;
  errorArchivo: string | null = null;
  metodoActivo: MetodoPagoCodigo | null = null;

  pagosDivididos: PagoDividRequest[] = [];
  pagosDivididosValidos = false;

  cargando = true;
  enviando = false;
  intentoEnviar = false;

  constructor(
    private servicio: PagoServicioService,
    private route: ActivatedRoute,
    private router: Router,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.numero = this.route.snapshot.paramMap.get('numero') ?? '';
    this.cargar();
  }

  private cargar(): void {
    this.servicio.config(this.numero).subscribe({
      next: (resp) => {
        // Sin saldo pagable no tiene sentido el formulario: vuelve al detalle.
        if (!resp.resumen.admite_pagos || !resp.resumen.consolidado || resp.resumen.saldo <= 0) {
          this.toastr.info(
            resp.resumen.en_verificacion > 0
              ? 'Tu pago está en verificación. Te avisamos apenas lo confirmemos.'
              : 'Este servicio no tiene saldo pendiente de pago.',
            'Servicio técnico',
          );
          this.volverAlDetalle();
          return;
        }

        this.resumen = resp.resumen;
        this.metodos = resp.metodos;
        this.datosTransferencia = resp.datos_transferencia;
        this.datosBilletera = resp.datos_billetera;
        this.mensajeEfectivo = resp.mensaje_efectivo;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No encontramos ese servicio en tu cuenta.', 'Servicio técnico');
        this.router.navigate(['/perfil-del-cliente'], { queryParams: { tab: 'servicios' }, replaceUrl: true });
      },
    });
  }

  volverAlDetalle(): void {
    this.router.navigate(['/mis-servicios', this.numero], { replaceUrl: true });
  }

  // ---------- Selección (misma lógica que paso-pago) ----------

  get esDividido(): boolean {
    return this.modoPago === 'dividido';
  }

  get metodoRequiereComprobante(): boolean {
    return this.metodos.find((m) => m.mpa_codigo === this.metodoSeleccionado)?.mpa_requcom ?? false;
  }

  get metodoParaDatos(): MetodoPagoCodigo | null {
    return this.esDividido ? this.metodoActivo : this.metodoSeleccionado;
  }

  get modoInvalido(): boolean {
    return this.intentoEnviar && !this.modoPago;
  }

  get metodoInvalido(): boolean {
    return this.intentoEnviar && !this.esDividido && !this.metodoSeleccionado;
  }

  get comprobanteInvalido(): boolean {
    return this.intentoEnviar && !this.esDividido && this.metodoRequiereComprobante && !this.comprobanteArchivo;
  }

  elegirModo(modo: ModoPago): void {
    if (this.modoPago === modo) return;

    this.modoPago = modo;
    this.metodoActivo = null;

    if (modo === 'dividido') {
      this.metodoSeleccionado = null;
      this.comprobanteArchivo = null;
    } else {
      this.pagosDivididos = [];
      this.pagosDivididosValidos = false;
    }
  }

  elegirMetodo(codigo: MetodoPagoCodigo): void {
    if (this.esDividido) return;
    this.metodoSeleccionado = codigo;
  }

  onCambioPagos(evento: { pagos: PagoDividRequest[]; valido: boolean }): void {
    this.pagosDivididos = evento.pagos;
    this.pagosDivididosValidos = evento.valido;
  }

  /** Misma validación de archivo que pago-dividido (extensión y 5 MB). */
  onArchivoSeleccionado(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;
    const extension = archivo?.name.split('.').pop()?.toLowerCase() ?? '';

    this.errorArchivo = null;
    this.comprobanteArchivo = null;

    if (archivo && !['jpg', 'jpeg', 'png', 'pdf'].includes(extension)) {
      this.errorArchivo = 'El comprobante debe ser JPG, PNG o PDF.';
    } else if (archivo && archivo.size > 5 * 1024 * 1024) {
      this.errorArchivo = 'El comprobante no puede superar los 5 MB.';
    } else {
      this.comprobanteArchivo = archivo;
    }

    input.value = '';
  }

  // ---------- Envío ----------

  get textoBoton(): string {
    const codigos = this.esDividido
      ? this.pagosDivididos.map((p) => p.mpa_codigo)
      : (this.metodoSeleccionado ? [this.metodoSeleccionado] : []);

    const requiere = (c: MetodoPagoCodigo) => this.metodos.find((m) => m.mpa_codigo === c)?.mpa_requcom ?? false;
    const conComprobante = codigos.some(requiere);
    const conEfectivo = codigos.some((c) => !requiere(c));

    if (conComprobante && conEfectivo) return 'Enviar y pagar el resto al retirar';
    if (conComprobante) return codigos.length > 1 ? 'Enviar comprobantes' : 'Enviar comprobante';
    if (conEfectivo) return 'Pagar al retirar mi equipo';

    return 'Confirmar pago';
  }

  get puedeEnviar(): boolean {
    if (!this.modoPago) return false;
    if (this.esDividido) return this.pagosDivididosValidos;
    if (!this.metodoSeleccionado) return false;

    return !this.metodoRequiereComprobante || !!this.comprobanteArchivo;
  }

  enviar(): void {
    if (this.enviando) return;

    this.intentoEnviar = true;
    if (!this.puedeEnviar) return;

    const payload: RegistrarPagoRequest = this.esDividido
      ? { modo: 'dividido', pagos: this.pagosDivididos }
      : { modo: 'unico', mpa_codigo: this.metodoSeleccionado!, comprobante: this.comprobanteArchivo };

    this.enviando = true;

    this.servicio.registrar(this.numero, payload).subscribe({
      next: (resp) => {
        this.enviando = false;
        this.toastr.success(resp.message, 'Servicio técnico');
        // replaceUrl: el formulario queda consumido (el saldo ya está comprometido).
        this.volverAlDetalle();
      },
      error: (e) => {
        this.enviando = false;
        const errores = e.error?.errors as Record<string, string[]> | undefined;
        const primero = errores ? Object.values(errores)[0]?.[0] : null;
        this.toastr.error(primero ?? e.error?.message ?? 'No pudimos registrar tu pago.', 'Servicio técnico');
      },
    });
  }
}
