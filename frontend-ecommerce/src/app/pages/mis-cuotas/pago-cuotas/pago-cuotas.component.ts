import { CommonModule, CurrencyPipe, DatePipe } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  ChequeDeclarado, DatosBilletera, DatosCheque, DatosTransferencia,
  MetodoPagoCodigo, MetodoPagoOpcion, ModoPago, PagoDividRequest,
} from '../../../core/models/checkout.model';
import {
  CuotaAPagar, RegistrarPagoCuotasRequest, SucursalCuotas,
} from '../../../core/models/cuota-pago.model';
import { MisCuotasService } from '../../../core/services/mis-cuotas.service';
import { ChequeFormComponent, chequeCompleto, chequeVacio } from '../../checkout/cheque-form/cheque-form.component';
import { PagoDivididoComponent } from '../../checkout/pago-dividido/pago-dividido.component';

/**
 * Pago de una o varias cuotas.
 *
 * Es el espejo del Paso 3 del checkout y reutiliza sus dos componentes
 * (app-pago-dividido y app-cheque-form): son literalmente las mismas
 * formas de pago. Las diferencias son de fondo y están todas acá:
 *
 *   - no hay stock que reservar ni plazo que corra: el pedido ya se
 *     entregó, lo que se paga es deuda;
 *   - el efectivo no es "contra entrega" sino pago en la sucursal;
 *   - el cheque no depende de la modalidad de entrega, sólo de que el
 *     admin lo tenga habilitado.
 *
 * Las cuotas vienen en la query, así que el link es reanudable.
 */
@Component({
  selector: 'app-pago-cuotas',
  standalone: true,
  imports: [
    CommonModule, CurrencyPipe, DatePipe, RouterModule,
    PagoDivididoComponent, ChequeFormComponent,
  ],
  templateUrl: './pago-cuotas.component.html',
  styleUrl: './pago-cuotas.component.css',
})
export class PagoCuotasComponent implements OnInit, OnDestroy {
  /** Segundos que permanece visible el aviso de "cheque no disponible". */
  private static readonly SEGUNDOS_AVISO_CHEQUE = 5;

  numero = '';
  ids: number[] = [];

  cuotas: CuotaAPagar[] = [];
  total = 0;
  facturaNumero = '';

  metodos: MetodoPagoOpcion[] = [];
  datosTransferencia!: DatosTransferencia;
  datosBilletera!: DatosBilletera;
  mensajeEfectivo: string | null = null;
  datosCheque!: DatosCheque;
  sucursal: SucursalCuotas | null = null;

  chequeHabilitado = false;
  motivoChequeNoDisponible: string | null = null;
  avisoChequeVisible = false;
  private temporizadorAviso?: ReturnType<typeof setTimeout>;

  modoPago: ModoPago | null = null;
  metodoSeleccionado: MetodoPagoCodigo | null = null;
  comprobanteArchivo: File | null = null;
  cheque: ChequeDeclarado = chequeVacio();

  metodoActivo: MetodoPagoCodigo | null = null;
  pagosDivididos: PagoDividRequest[] = [];
  pagosDivididosValidos = false;

  cargando = true;
  enviando = false;
  intentoEnviar = false;

  constructor(
    private servicio: MisCuotasService,
    private route: ActivatedRoute,
    private router: Router,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.numero = this.route.snapshot.paramMap.get('numero') ?? '';

    this.ids = (this.route.snapshot.queryParamMap.get('cuotas') ?? '')
      .split(',')
      .map((v) => Number(v))
      .filter((v) => v > 0);

    if (this.ids.length === 0) {
      this.router.navigate(['/mis-cuotas', this.numero], { replaceUrl: true });
      return;
    }

    this.cargar();
  }

  ngOnDestroy(): void {
    if (this.temporizadorAviso) clearTimeout(this.temporizadorAviso);
  }

  private cargar(): void {
    this.cargando = true;

    this.servicio.configPago(this.numero, this.ids).subscribe({
      next: (resp) => {
        this.cuotas = resp.cuotas;
        this.total = resp.total;
        this.facturaNumero = resp.factura.numero;

        this.metodos = resp.metodos;
        this.datosTransferencia = resp.datos_transferencia;
        this.datosBilletera = resp.datos_billetera;
        this.mensajeEfectivo = resp.mensaje_efectivo;
        this.datosCheque = resp.datos_cheque;
        this.chequeHabilitado = resp.cheque_habilitado;
        this.motivoChequeNoDisponible = resp.cheque_motivo;
        this.sucursal = resp.sucursal;

        this.cargando = false;
      },
      error: (e) => {
        // 422 con clave 'cuotas': ya no son pagables (otro pago en curso,
        // cobradas, o la selección dejó de ser consecutiva). En vez de un
        // formulario que siempre falla, se vuelve al detalle.
        const mensaje = e.error?.errors?.['cuotas']?.[0] ?? e.error?.message;

        this.toastr.info(mensaje ?? 'No pudimos abrir el pago de estas cuotas.', 'Cuotas');
        this.router.navigate(['/mis-cuotas', this.numero], { replaceUrl: true });
      },
    });
  }

  // ---------- Selección ----------

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
    return this.intentoEnviar
      && !this.esDividido
      && this.metodoRequiereComprobante
      && !this.comprobanteArchivo;
  }

  elegirModo(modo: ModoPago): void {
    if (this.modoPago === modo) return;

    this.modoPago = modo;
    this.metodoActivo = null;

    // Al cambiar de modo se descarta lo cargado en el otro: son dos
    // formas distintas de cubrir el mismo total.
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

    // En pago dividido la tarjeta es sólo informativa.
    if (this.esDividido) return;

    this.metodoSeleccionado = codigo;
  }

  private mostrarAvisoCheque(): void {
    this.avisoChequeVisible = true;

    if (this.temporizadorAviso) clearTimeout(this.temporizadorAviso);

    this.temporizadorAviso = setTimeout(() => {
      this.avisoChequeVisible = false;
    }, PagoCuotasComponent.SEGUNDOS_AVISO_CHEQUE * 1000);
  }

  onCambioPagos(evento: { pagos: PagoDividRequest[]; valido: boolean }): void {
    this.pagosDivididos = evento.pagos;
    this.pagosDivididosValidos = evento.valido;
  }

  onArchivoSeleccionado(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    this.comprobanteArchivo = input.files?.[0] ?? null;
  }

  onChequeCambio(cheque: ChequeDeclarado): void {
    this.cheque = cheque;
  }

  // ---------- Envío ----------

  /**
   * El rótulo sale de la composición real del pago, no de un if por
   * código de método: así el pago dividido con dos transferencias cae
   * solo en el texto correcto.
   */
  get textoBoton(): string {
    const brutos: (MetodoPagoCodigo | null)[] = this.esDividido
      ? this.pagosDivididos.map((p) => p.mpa_codigo)
      : [this.metodoSeleccionado];

    const codigos = brutos.filter((c): c is MetodoPagoCodigo => !!c);

    const requiere = (c: MetodoPagoCodigo) =>
      this.metodos.find((m) => m.mpa_codigo === c)?.mpa_requcom ?? false;

    const conComprobante = codigos.some(requiere);
    const conCheque = codigos.some((c) => c === 'CHEQUE');
    const conEfectivo = codigos.some((c) => c !== 'CHEQUE' && !requiere(c));

    const clases = [conComprobante, conCheque, conEfectivo].filter(Boolean).length;

    if (clases > 1) return 'Enviar y confirmar pago';
    if (conComprobante) return codigos.length > 1 ? 'Enviar comprobantes' : 'Enviar comprobante';
    if (conCheque) return 'Confirmar pago con cheque';
    if (conEfectivo) return 'Confirmar pago en sucursal';

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

    const payload: RegistrarPagoCuotasRequest = this.esDividido
      ? { modo: 'dividido', pagos: this.pagosDivididos }
      : {
          modo: 'unico',
          mpa_codigo: this.metodoSeleccionado!,
          comprobante: this.comprobanteArchivo,
          // Sólo viaja si el método elegido es cheque: los datos de un
          // cheque cargado y luego abandonado no llegan al backend.
          cheque: this.metodoEsCheque ? this.cheque : null,
        };

    this.enviando = true;

    this.servicio.pagar(this.ids, payload).subscribe({
      next: (resp) => {
        this.enviando = false;

        // replaceUrl: el formulario queda consumido. Volver con el botón
        // del navegador llevaría a cuotas que ya no son pagables.
        this.router.navigate(['/mis-cuotas', this.numero, 'pago', resp.data.cpa_id], {
          replaceUrl: true,
        });
      },
      error: (e) => {
        this.enviando = false;

        const errores = e.error?.errors as Record<string, string[]> | undefined;
        const primero = errores ? Object.values(errores)[0]?.[0] : null;

        this.toastr.error(primero ?? e.error?.message ?? 'No pudimos registrar tu pago.', 'Error');
      },
    });
  }

  cancelar(): void {
    this.router.navigate(['/mis-cuotas', this.numero]);
  }
}