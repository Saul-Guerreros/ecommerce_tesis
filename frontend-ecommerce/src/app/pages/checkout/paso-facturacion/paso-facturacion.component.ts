import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { forkJoin, switchMap } from 'rxjs';
import {
  DatosBilletera, DatosTransferencia, EntregaCheckout, MedioRecepcion,
  GuardarPagoRequest, MetodoPagoCodigo, MetodoPagoOpcion, ModoPago, PagoDividRequest,
  PagoItemCheckout, ResumenCompra, TipoDocumentoFacturacion, TitularFacturacion,
} from '../../../core/models/checkout.model';
import { DatoFiscal } from '../../../core/models/dato-fiscal.model';
import { CheckoutService } from '../../../core/services/checkout.service';
import { DatoFiscalClienteService } from '../../../core/services/dato-fiscal-cliente.service';
import { FormDatoFiscalComponent } from '../form-dato-fiscal/form-dato-fiscal.component';
import { PagoDivididoComponent } from '../pago-dividido/pago-dividido.component';

@Component({
  selector: 'app-paso-facturacion',
  standalone: true,
  imports: [
    CommonModule, CurrencyPipe, ReactiveFormsModule,
    FormDatoFiscalComponent, PagoDivididoComponent,
  ],
  templateUrl: './paso-facturacion.component.html',
  styleUrl: './paso-facturacion.component.css',
})
export class PasoFacturacionComponent implements OnInit {
  tipoDocumento: TipoDocumentoFacturacion = 'CI';

  /**
   * Sin valor por defecto a proposito: la primera vez que el cliente
   * pasa por este paso, ninguna opcion debe aparecer marcada. Solo se
   * completa si ya habia una eleccion previa guardada en este mismo
   * pedido (ver cargar()).
   */
  medioRecepcion: MedioRecepcion | null = null;

  titular!: TitularFacturacion;
  entrega: EntregaCheckout | null = null;
  resumen!: ResumenCompra;

  datosFiscales: DatoFiscal[] = [];
  datoFiscalSeleccionado: DatoFiscal | null = null;

  // Método de pago
  metodos: MetodoPagoOpcion[] = [];
  metodoSeleccionado: MetodoPagoCodigo | null = null;
  datosTransferencia!: DatosTransferencia;
  datosBilletera!: DatosBilletera;
  mensajeEfectivo: string | null = null;
  comprobanteArchivo: File | null = null;
  comprobanteNombreGuardado: string | null = null;

  /**
   * Sin valor por defecto, mismo criterio que medioRecepcion: la primera
   * vez ninguna opcion aparece marcada.
   */
  modoPago: ModoPago | null = null;

  /** Total exigible; lo calcula el backend, nunca esta pantalla. */
  totalPedido = 0;

  /**
   * Metodo del bloque que el cliente esta editando en el pago dividido.
   * Es lo que decide que datos muestra la tarjeta "Método de Pago".
   */
  metodoActivo: MetodoPagoCodigo | null = null;

  pagosGuardados: PagoItemCheckout[] | null = null;
  pagosDivididos: PagoDividRequest[] = [];
  pagosDivididosValidos = false;

  cargando = true;
  guardando = false;
  modalVisible = false;
  datoFiscalEditar: DatoFiscal | null = null;

  /**
   * true apenas el cliente intenta continuar sin completar algo. A
   * partir de ahi los mensajes en rojo de abajo se recalculan solos a
   * medida que corrige cada campo (son getters, no un snapshot fijo).
   */
  intentoContinuar = false;

  get medioInvalido(): boolean {
    return this.intentoContinuar && !this.medioRecepcion;
  }

  get modoInvalido(): boolean {
    return this.intentoContinuar && !this.modoPago;
  }

  get esDividido(): boolean {
    return this.modoPago === 'dividido';
  }

  get metodoInvalido(): boolean {
    return this.intentoContinuar && !this.esDividido && !this.metodoSeleccionado;
  }

  get comprobanteInvalido(): boolean {
    return this.intentoContinuar
      && !this.esDividido
      && this.metodoRequiereComprobante
      && !this.comprobanteArchivo
      && !this.comprobanteNombreGuardado;
  }

  /**
   * Metodo cuyos datos (banco, numero de Tigo, mensaje de efectivo) se
   * muestran en la tarjeta superior. Con un solo metodo es el elegido; con
   * pago dividido es el del bloque que el cliente esta tocando.
   */
  get metodoParaDatos(): MetodoPagoCodigo | null {
    return this.esDividido ? this.metodoActivo : this.metodoSeleccionado;
  }

  constructor(
    private checkoutService: CheckoutService,
    private datoFiscalService: DatoFiscalClienteService,
    private toastr: ToastrService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.cargar();
  }

  private cargar(): void {
    this.cargando = true;

    forkJoin({
      facturacion: this.checkoutService.configFacturacion(),
      pago: this.checkoutService.configPago(),
    }).subscribe({
      next: ({ facturacion, pago }) => {
                if (!facturacion.entrega) {
                  // cargando queda en true a proposito: mantiene el spinner y evita
                  // que la pagina se dibuje por un instante antes de redirigir.
                  this.toastr.info('Tu checkout anterior ya no está disponible.', 'Checkout');
                  this.router.navigate(['/carrito-de-compra'], { replaceUrl: true });
                  return;
              }

        this.titular = facturacion.titular;
        this.datosFiscales = facturacion.datos_fiscales;
        this.entrega = facturacion.entrega;
        this.resumen = facturacion.resumen;

        if (facturacion.facturacion) {
          this.tipoDocumento = facturacion.facturacion.tipo_documento;
          this.medioRecepcion = facturacion.facturacion.pfa_recibe;
        }

        // La predeterminada es siempre el respaldo, no solo cuando no
        // hay ninguna facturacion guardada todavia en este pedido. Si
        // la vez anterior el cliente habia elegido C.I. (o un RUC que
        // ya no existe), esto evita que la seccion RUC quede sin nada
        // marcado apenas se cambia a esa pestaña.
        const dfiIdGuardado = facturacion.facturacion?.tipo_documento === 'RUC'
          ? facturacion.facturacion.dfi_id
          : null;

        this.datoFiscalSeleccionado =
          (dfiIdGuardado && this.datosFiscales.find((d) => d.dfi_id === dfiIdGuardado))
          || this.datosFiscales.find((d) => d.dfi_principal)
          || this.datosFiscales[0]
          || null;

        this.metodos = pago.metodos;
        this.datosTransferencia = pago.datos_transferencia;
        this.datosBilletera = pago.datos_billetera;
        this.mensajeEfectivo = pago.mensaje_efectivo;

        this.totalPedido = pago.total_pedido;

        if (pago.pago) {
          this.modoPago = pago.pago.modo;

          if (pago.pago.modo === 'dividido') {
            this.pagosGuardados = pago.pago.pagos;
            this.metodoActivo = pago.pago.pagos[0]?.mpa_codigo ?? null;
          } else {
            this.metodoSeleccionado = pago.pago.mpa_codigo;
            this.comprobanteNombreGuardado = pago.pago.tiene_comprobante ? 'Comprobante ya cargado' : null;
          }
        }

        this.cargando = false;
      },
      error: () => {
        this.cargando = false;
        this.toastr.error('No pudimos cargar tus datos de facturación.', 'Error');
        this.router.navigate(['/proceso-de-pago/entrega'], { replaceUrl: true });
      },
    });
  }

  get esRetiro(): boolean {
    return this.entrega?.modalidad === 'retiro';
  }

  get total(): number {
    return this.resumen.subtotal + this.resumen.costo_envio;
  }

  get metodoRequiereComprobante(): boolean {
    return this.metodos.find((m) => m.mpa_codigo === this.metodoSeleccionado)?.mpa_requcom ?? false;
  }

  elegirTipoDocumento(tipo: TipoDocumentoFacturacion): void {
    this.tipoDocumento = tipo;
  }

  seleccionarDatoFiscal(dato: DatoFiscal): void {
    this.datoFiscalSeleccionado = dato;
  }

  abrirNuevo(): void {
    this.datoFiscalEditar = null;
    this.modalVisible = true;
  }

  abrirEditar(): void {
    if (!this.datoFiscalSeleccionado) return;
    this.datoFiscalEditar = this.datoFiscalSeleccionado;
    this.modalVisible = true;
  }

  eliminar(dato: DatoFiscal, evento: Event): void {
    evento.stopPropagation();
    if (!confirm('¿Eliminar este dato fiscal?')) return;

    this.datoFiscalService.eliminar(dato.dfi_id).subscribe({
      next: (resp) => {
        this.toastr.success(resp.message, 'Datos fiscales');
        this.datosFiscales = this.datosFiscales.filter((d) => d.dfi_id !== dato.dfi_id);
        if (this.datoFiscalSeleccionado?.dfi_id === dato.dfi_id) {
          this.datoFiscalSeleccionado = this.datosFiscales.find((d) => d.dfi_principal) ?? this.datosFiscales[0] ?? null;
        }
      },
      error: (err) => {
        const mensaje = err.error?.message || 'No se pudo eliminar el dato fiscal.';
        this.toastr.error(mensaje, 'Error');
      },
    });
  }

  /**
   * El modal ahora siempre persiste (ya no existe el RUC de "uso
   * unico"): esto solo actualiza la lista local y deja seleccionado
   * el que se acaba de crear/editar. Si vino marcado como
   * predeterminado, el backend ya desmarco a los demas -- se refleja
   * eso mismo en memoria para no tener que recargar toda la lista.
   */
  onGuardado(datoFiscal: DatoFiscal): void {
    this.modalVisible = false;

    const esEdicion = this.datosFiscales.some((d) => d.dfi_id === datoFiscal.dfi_id);
    let lista = esEdicion
      ? this.datosFiscales.map((d) => (d.dfi_id === datoFiscal.dfi_id ? datoFiscal : d))
      : [datoFiscal, ...this.datosFiscales];

    if (datoFiscal.dfi_principal) {
      lista = lista.map((d) => ({ ...d, dfi_principal: d.dfi_id === datoFiscal.dfi_id }));
    }

    this.datosFiscales = lista;
    this.datoFiscalSeleccionado = datoFiscal;
  }

  elegirMedio(medio: MedioRecepcion): void {
    this.medioRecepcion = medio;
  }

  elegirMetodo(codigo: MetodoPagoCodigo): void {
    // En pago dividido la tarjeta es solo informativa: el metodo se elige
    // dentro de cada bloque de pago.
    if (this.esDividido) return;

    this.metodoSeleccionado = codigo;
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
      this.comprobanteNombreGuardado = null;
    } else {
      this.pagosDivididos = [];
      this.pagosDivididosValidos = false;
    }
  }

  onCambioPagos(evento: { pagos: PagoDividRequest[]; valido: boolean }): void {
    this.pagosDivididos = evento.pagos;
    this.pagosDivididosValidos = evento.valido;
  }

  onArchivoSeleccionado(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    this.comprobanteArchivo = input.files?.[0] ?? null;
  }

  continuar(): void {
    if (this.guardando) return;

    // A partir de aca los 3 getters de arriba (medioInvalido,
    // metodoInvalido, comprobanteInvalido) empiezan a mostrar su
    // mensaje en rojo si corresponde, sin tener que repetir la
    // condicion aca.
    this.intentoContinuar = true;

    if (this.tipoDocumento === 'RUC' && !this.datoFiscalSeleccionado) {
      this.toastr.error('Elegí o cargá un RUC.', 'Validación');
      return;
    }

    if (!this.medioRecepcion || !this.modoPago) {
      return; // el mensaje correspondiente ya quedo visible en el formulario
    }

    if (this.esDividido) {
      // Los mensajes por bloque los muestra el propio componente hijo.
      if (!this.pagosDivididosValidos) return;
    } else if (!this.metodoSeleccionado || this.comprobanteInvalido) {
      return;
    }

    const payload = this.tipoDocumento === 'CI'
      ? { tipo_documento: 'CI' as const, pfa_recibe: this.medioRecepcion }
      : { tipo_documento: 'RUC' as const, dfi_id: this.datoFiscalSeleccionado!.dfi_id, pfa_recibe: this.medioRecepcion };

    const pagoPayload: GuardarPagoRequest = this.esDividido
      ? { modo: 'dividido', pagos: this.pagosDivididos }
      : { modo: 'unico', mpa_codigo: this.metodoSeleccionado!, comprobante: this.comprobanteArchivo };

    this.guardando = true;
    this.checkoutService.guardarFacturacion(payload).pipe(
      switchMap(() => this.checkoutService.guardarPago(pagoPayload)),
    ).subscribe({
      next: () => {
        this.guardando = false;
        this.toastr.success('Datos guardados.', 'Listo');
        this.router.navigate(['/proceso-de-pago/confirmacion'], { replaceUrl: true });
      },
      error: (e) => {
        this.guardando = false;
        if (e.status === 422 && e.error?.errors) {
          // El backend revalida la suma contra el total vigente: si cambio
          // el carrito, ese mensaje es mas util que un "revisá los datos".
          const errores = e.error.errors as Record<string, string[]>;
          const primero = errores['pagos']?.[0] ?? Object.values(errores)[0]?.[0];

          this.toastr.error(primero ?? 'Revisá los datos marcados.', 'Validación');
          return;
        }
        this.toastr.error(e.error?.message ?? 'No pudimos guardar los datos.', 'Error');
      },
    });
  }

  volver(): void {
   this.router.navigate(['/proceso-de-pago/entrega'], { replaceUrl: true });
  }
  
}