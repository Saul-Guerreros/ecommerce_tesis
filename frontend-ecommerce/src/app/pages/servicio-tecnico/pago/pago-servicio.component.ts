import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnDestroy, OnInit, ViewChild } from '@angular/core';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { of, switchMap } from 'rxjs';
import {
  ChequeDeclarado, DatosBilletera, DatosCheque, DatosTransferencia, MetodoPagoCodigo, MetodoPagoOpcion,
  ModoPago, PagoDividRequest, RegistrarPagoRequest,
} from '../../../core/models/checkout.model';
import { ResumenPagoServicio } from '../../../core/models/servicio-tecnico.model';
import { PagoServicioService } from '../../../core/services/pago-servicio.service';
import { ChequeFormComponent, chequeCompleto, chequeVacio } from '../../checkout/cheque-form/cheque-form.component';
import { PagoDivididoComponent } from '../../checkout/pago-dividido/pago-dividido.component';
import { ERROR_FACTURACION_SERVICIO, FacturacionServicioComponent } from '../facturacion/facturacion-servicio.component';

/**
 * C4 - Pagar el saldo del servicio técnico (/mis-servicios/:numero/pago).
 * Imita el Paso 3 del checkout: mismas tarjetas, app-pago-dividido con el
 * SALDO como total y app-cheque-form. El cheque se ofrece según la
 * modalidad del servicio; si no está disponible, explica por qué.
 */
@Component({
  selector: 'app-pago-servicio',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, RouterModule, PagoDivididoComponent, ChequeFormComponent, FacturacionServicioComponent],
  templateUrl: './pago-servicio.component.html',
  styleUrls: ['../../checkout/paso-pago/paso-pago.component.css', './pago-servicio.component.css'],
})
export class PagoServicioComponent implements OnInit, OnDestroy {
  private static readonly SEGUNDOS_AVISO_CHEQUE = 5;

  @ViewChild(FacturacionServicioComponent) facturacion?: FacturacionServicioComponent;

  numero = '';
  resumen!: ResumenPagoServicio;

  metodos: MetodoPagoOpcion[] = [];
  datosTransferencia!: DatosTransferencia;
  datosBilletera!: DatosBilletera;
  mensajeEfectivo: string | null = null;
  datosCheque!: DatosCheque;

  chequeHabilitado = false;
  motivoChequeNoDisponible: string | null = null;
  avisoChequeVisible = false;
  private temporizadorAviso?: ReturnType<typeof setTimeout>;

  modoPago: ModoPago | null = null;
  metodoSeleccionado: MetodoPagoCodigo | null = null;
  comprobanteArchivo: File | null = null;
  errorArchivo: string | null = null;
  cheque: ChequeDeclarado = chequeVacio();
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

  ngOnDestroy(): void {
    if (this.temporizadorAviso) clearTimeout(this.temporizadorAviso);
  }

  private cargar(): void {
    this.servicio.config(this.numero).subscribe({
      next: (resp) => {
        if (!resp.resumen.admite_pagos || !resp.resumen.consolidado || resp.resumen.saldo <= 0) {
          this.toastr.info(
            resp.resumen.cheque_pendiente > 0
              ? 'Tu pago con cheque está declarado: acercá el cheque a la sucursal dentro del plazo.'
              : resp.resumen.en_verificacion > 0
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
        this.datosCheque = resp.datos_cheque;
        this.chequeHabilitado = resp.cheque_habilitado;
        this.motivoChequeNoDisponible = resp.cheque_motivo;
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

  get esDividido(): boolean {
    return this.modoPago === 'dividido';
  }

  get metodoEsCheque(): boolean {
    return this.metodoSeleccionado === 'CHEQUE';
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

  /** En verificación + cheques por entregar: lo comprometido que todavía no se cobró. */
  get pendienteDeConfirmar(): number {
    return this.resumen.comprometido - this.resumen.confirmado;
  }

  elegirModo(modo: ModoPago): void {
    if (this.modoPago === modo) return;

    this.modoPago = modo;
    this.metodoActivo = null;

    if (modo === 'dividido') {
      this.metodoSeleccionado = null;
      this.comprobanteArchivo = null;
      this.cheque = chequeVacio();
    } else {
      this.pagosDivididos = [];
      this.pagosDivididosValidos = false;
    }
  }

  elegirMetodo(codigo: MetodoPagoCodigo): void {
    if (codigo === 'CHEQUE' && !this.chequeHabilitado) {
      this.mostrarAvisoCheque();
      return;
    }

    if (this.esDividido) return;
    this.metodoSeleccionado = codigo;
  }

  private mostrarAvisoCheque(): void {
    this.avisoChequeVisible = true;

    if (this.temporizadorAviso) clearTimeout(this.temporizadorAviso);

    this.temporizadorAviso = setTimeout(() => {
      this.avisoChequeVisible = false;
    }, PagoServicioComponent.SEGUNDOS_AVISO_CHEQUE * 1000);
  }

  onCambioPagos(evento: { pagos: PagoDividRequest[]; valido: boolean }): void {
    this.pagosDivididos = evento.pagos;
    this.pagosDivididosValidos = evento.valido;
  }

  onChequeCambio(cheque: ChequeDeclarado): void {
    this.cheque = cheque;
  }

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

  /** Mismas tres clases de pago que paso-pago: comprobante, cheque y efectivo. */
  get textoBoton(): string {
    const codigos = this.esDividido
      ? this.pagosDivididos.map((p) => p.mpa_codigo)
      : (this.metodoSeleccionado ? [this.metodoSeleccionado] : []);

    const requiere = (c: MetodoPagoCodigo) => this.metodos.find((m) => m.mpa_codigo === c)?.mpa_requcom ?? false;
    const conComprobante = codigos.some(requiere);
    const conCheque = codigos.some((c) => c === 'CHEQUE');
    const conEfectivo = codigos.some((c) => c !== 'CHEQUE' && !requiere(c));

    const clases = [conComprobante, conCheque, conEfectivo].filter(Boolean).length;

    if (clases > 1) return 'Enviar y confirmar pago';
    if (conComprobante) return codigos.length > 1 ? 'Enviar comprobantes' : 'Enviar comprobante';
    if (conCheque) return codigos.length > 1 ? 'Confirmar pagos con cheque' : 'Confirmar pago con cheque';
    if (conEfectivo) return 'Pagar al retirar mi equipo';

    return 'Confirmar pago';
  }

  get puedeEnviar(): boolean {
    if (!this.modoPago) return false;
    if (this.esDividido) return this.pagosDivididosValidos;
    if (!this.metodoSeleccionado) return false;
    if (this.metodoEsCheque) return chequeCompleto(this.cheque);

    return !this.metodoRequiereComprobante || !!this.comprobanteArchivo;
  }

  enviar(): void {
    if (this.enviando) return;

    this.intentoEnviar = true;
    if (!this.puedeEnviar) return;

    const payload: RegistrarPagoRequest = this.esDividido
      ? { modo: 'dividido', pagos: this.pagosDivididos }
      : {
          modo: 'unico',
          mpa_codigo: this.metodoSeleccionado!,
          comprobante: this.comprobanteArchivo,
          cheque: this.metodoEsCheque ? this.cheque : null,
        };

    this.enviando = true;

    const facturacion$ = this.facturacion ? this.facturacion.guardar() : of(null);

    facturacion$.pipe(switchMap(() => this.servicio.registrar(this.numero, payload))).subscribe({
      next: (resp) => {
        this.enviando = false;
        this.toastr.success(resp.message, 'Servicio técnico');
        this.volverAlDetalle();
      },
      error: (e) => {
        this.enviando = false;
        if (e === ERROR_FACTURACION_SERVICIO) {
          this.toastr.warning('Revisá los datos de facturación.', 'Servicio técnico');
          return;
        }
        const errores = e.error?.errors as Record<string, string[]> | undefined;
        const primero = errores ? Object.values(errores)[0]?.[0] : null;
        this.toastr.error(primero ?? e.error?.message ?? 'No pudimos registrar tu pago.', 'Servicio técnico');
      },
    });
  }
}