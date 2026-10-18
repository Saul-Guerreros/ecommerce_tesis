import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  DatosBilletera, DatosTransferencia, MetodoPagoCodigo, MetodoPagoOpcion,
  ModoPago, PagoDividRequest, PedidoAPagar, RegistrarPagoRequest, ResumenCompra,
} from '../../../core/models/checkout.model';
import {
  EntregaResumenCard, FacturacionResumenCard, ProductoResumenCardItem,
} from '../../../core/models/resumen-compra.model';
import { PagoPedidoService } from '../../../core/services/pago-pedido.service';
import { PagoDivididoComponent } from '../pago-dividido/pago-dividido.component';
import { ProductosCarritoCardComponent } from '../../../shared/components/productos-carrito-card/productos-carrito-card.component';
import { DatosEntregaCardComponent } from '../../../shared/components/datos-entrega-card/datos-entrega-card.component';
import { DatosFacturacionCardComponent } from '../../../shared/components/datos-facturacion-card/datos-facturacion-card.component';

/**
 * Paso 3 del checkout: el cliente declara con qué pagó.
 *
 * A diferencia de los pasos anteriores, acá el pedido YA existe y tiene el
 * stock reservado a nombre del cliente. Eso cambia dos cosas de fondo
 * respecto del flujo anterior:
 *
 *   - No hay borrador. El pago se declara una sola vez y queda escrito.
 *   - La pantalla es reanudable: el número de pedido está en la URL, así
 *     que el cliente puede cerrar el navegador y volver al link mientras
 *     la reserva siga vigente.
 */
@Component({
  selector: 'app-paso-pago',
  standalone: true,
  imports: [
    CommonModule, CurrencyPipe, PagoDivididoComponent,
    ProductosCarritoCardComponent, DatosEntregaCardComponent, DatosFacturacionCardComponent,
  ],
  templateUrl: './paso-pago.component.html',
  styleUrl: './paso-pago.component.css',
})
export class PasoPagoComponent implements OnInit, OnDestroy {
  numero = '';
  pedido!: PedidoAPagar;

  metodos: MetodoPagoOpcion[] = [];
  datosTransferencia!: DatosTransferencia;
  datosBilletera!: DatosBilletera;
  mensajeEfectivo: string | null = null;

  /** Tarjetas de resumen de la columna lateral. */
  resumen!: ResumenCompra;
  productos: ProductoResumenCardItem[] = [];
  entrega: EntregaResumenCard | null = null;
  facturacion: FacturacionResumenCard | null = null;

  /**
   * Sin valor por defecto: la primera vez ninguna opción aparece marcada.
   */
  modoPago: ModoPago | null = null;
  metodoSeleccionado: MetodoPagoCodigo | null = null;
  comprobanteArchivo: File | null = null;

  /**
   * Método del bloque que el cliente está editando en el pago dividido.
   * Es lo que decide qué datos muestra la tarjeta "Método de Pago".
   */
  metodoActivo: MetodoPagoCodigo | null = null;

  pagosDivididos: PagoDividRequest[] = [];
  pagosDivididosValidos = false;

  cargando = true;
  enviando = false;
  intentoEnviar = false;

  /** Texto del plazo restante. Se refresca solo cada minuto. */
  restante = '';
  private reloj?: ReturnType<typeof setInterval>;

  constructor(
    private servicio: PagoPedidoService,
    private route: ActivatedRoute,
    private toastr: ToastrService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.numero = this.route.snapshot.paramMap.get('numero') ?? '';
    this.cargar();
  }

  ngOnDestroy(): void {
    if (this.reloj) clearInterval(this.reloj);
  }

  private cargar(): void {
    this.cargando = true;

    this.servicio.config(this.numero).subscribe({
      next: (resp) => {
        this.pedido = resp.pedido;
        this.metodos = resp.metodos;
        this.datosTransferencia = resp.datos_transferencia;
        this.datosBilletera = resp.datos_billetera;
        this.mensajeEfectivo = resp.mensaje_efectivo;

        // Ya vienen normalizados del backend: ninguno necesita adaptarse.
        this.resumen = resp.resumen;
        this.productos = resp.productos;
        this.entrega = resp.entrega;
        this.facturacion = resp.facturacion;

        this.actualizarRestante();
        this.reloj = setInterval(() => this.actualizarRestante(), 60_000);

        this.cargando = false;
      },
      error: (e) => {
        // 422 con clave 'pedido': ya fue pagado o venció. En vez de dejar
        // un formulario que no sirve, se manda al lugar correcto.
        const mensaje = e.error?.errors?.['pedido']?.[0] ?? e.error?.message;

        if (e.error?.errors?.['pedido']) {
          this.toastr.info(mensaje, 'Pedido');
          this.router.navigate(['/pedido-resultado', this.numero], { replaceUrl: true });
          return;
        }

        this.toastr.error(mensaje ?? 'No pudimos cargar el pago de tu pedido.', 'Error');
        this.router.navigate(['/'], { replaceUrl: true });
      },
    });
  }

  // ---------- Plazo ----------

  private actualizarRestante(): void {
    const vence = new Date(this.pedido.ped_vence).getTime();
    const faltan = vence - Date.now();

    if (faltan <= 0) {
      this.restante = 'vencido';
      return;
    }

    const horas = Math.floor(faltan / 3_600_000);
    const minutos = Math.floor((faltan % 3_600_000) / 60_000);

    this.restante = horas > 0 ? `${horas} h ${minutos} min` : `${minutos} min`;
  }

  // ---------- Selección ----------

  get esDividido(): boolean {
    return this.modoPago === 'dividido';
  }

  get metodoRequiereComprobante(): boolean {
    return this.metodos.find((m) => m.mpa_codigo === this.metodoSeleccionado)?.mpa_requcom ?? false;
  }

  /**
   * Método cuyos datos (banco, número de Tigo, mensaje de efectivo) se
   * muestran en la tarjeta. Con un solo método es el elegido; con pago
   * dividido es el del bloque que el cliente está tocando.
   */
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

    // Al cambiar de modo se descarta lo cargado en el otro: son dos formas
    // distintas de cubrir el mismo total y mezclarlas no tiene sentido.
    if (modo === 'dividido') {
      this.metodoSeleccionado = null;
      this.comprobanteArchivo = null;
    } else {
      this.pagosDivididos = [];
      this.pagosDivididosValidos = false;
    }
  }

  elegirMetodo(codigo: MetodoPagoCodigo): void {
    // En pago dividido la tarjeta es sólo informativa: el método se elige
    // dentro de cada bloque de pago.
    if (this.esDividido) return;

    this.metodoSeleccionado = codigo;
  }

  onCambioPagos(evento: { pagos: PagoDividRequest[]; valido: boolean }): void {
    this.pagosDivididos = evento.pagos;
    this.pagosDivididosValidos = evento.valido;
  }

  onArchivoSeleccionado(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    this.comprobanteArchivo = input.files?.[0] ?? null;
  }

  // ---------- Botón de envío ----------

  /**
   * Los tres rótulos del instructivo salen de la composición real del
   * pago, no de un if por código de método:
   *
   *   sólo comprobante          -> "Enviar comprobante"
   *   comprobante + efectivo    -> "Enviar y confirmar pago"
   *   sólo efectivo             -> "Confirmar pago contra entrega"
   *
   * Calcularlo así hace que el pago dividido con dos transferencias caiga
   * solo en el rótulo correcto, sin un caso especial.
   */
  get textoBoton(): string {
    const codigos = this.esDividido
      ? this.pagosDivididos.map((p) => p.mpa_codigo)
      : (this.metodoSeleccionado ? [this.metodoSeleccionado] : []);

    const requiere = (c: MetodoPagoCodigo) =>
      this.metodos.find((m) => m.mpa_codigo === c)?.mpa_requcom ?? false;

    const conComprobante = codigos.some(requiere);
    const conEfectivo = codigos.some((c) => !requiere(c));

    if (conComprobante && conEfectivo) return 'Enviar y confirmar pago';
    if (conComprobante) return codigos.length > 1 ? 'Enviar comprobantes' : 'Enviar comprobante';
    if (conEfectivo) return 'Confirmar pago contra entrega';

    return 'Confirmar pago';
  }

  /**
   * El botón se habilita recién cuando el pago está completo: con
   * comprobante adjunto si el método lo exige, o con todos los bloques
   * válidos en el pago dividido. Es lo que pide el instructivo.
   */
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
      next: () => {
        this.enviando = false;

        // replaceUrl: el formulario de pago queda consumido. Volver con el
        // botón del navegador llevaría a un pedido que ya no es pagable.
        this.router.navigate(['/pedido-resultado', this.numero], { replaceUrl: true });
      },
      error: (e) => {
        this.enviando = false;

        const errores = e.error?.errors as Record<string, string[]> | undefined;
        const primero = errores ? Object.values(errores)[0]?.[0] : null;

        this.toastr.error(primero ?? e.error?.message ?? 'No pudimos registrar tu pago.', 'Error');
      },
    });
  }
}