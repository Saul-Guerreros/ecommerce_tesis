import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  AlertModule, BadgeModule, ButtonModule, CardModule, FormModule,
  ProgressModule, SpinnerModule, TableModule, TooltipModule, ModalModule,
} from '@coreui/angular';

import { ChequePago, PagoItem, PedidoDetalle } from '../models/pedido-detalle.model';
import { PedidosService } from '../service/pedidos.service';
import { ValidarComprobanteComponent } from '../validar-comprobante/validar-comprobante.component';
import { RealizarCobroComponent } from '../realizar-cobro/realizar-cobro.component';
import { RechazarPagoComponent } from '../rechazar-pago/rechazar-pago.component';
import { RecibirChequeComponent } from '../recibir-cheque/recibir-cheque.component';
import { RechazarChequeComponent } from '../rechazar-cheque/rechazar-cheque.component';
/**
 * Pantalla de Gestión del Pedido.
 *
 * Todas las respuestas de validar/cobrar/rechazar/cambiar estado devuelven
 * el pedido completo, así que la pantalla se refresca con ese payload en
 * lugar de volver a pedir el detalle. Evita un segundo request y, sobre
 * todo, evita que la pantalla quede un instante mostrando datos viejos.
 */
@Component({
  selector: 'app-detalle-pedido',
  standalone: true,
  imports: [
    CommonModule, FormsModule, ReactiveFormsModule, CardModule, TableModule,
    ButtonModule, BadgeModule, SpinnerModule, FormModule, AlertModule,
    ProgressModule, TooltipModule, ValidarComprobanteComponent,
    RealizarCobroComponent, RechazarPagoComponent, ModalModule,
    RecibirChequeComponent, RechazarChequeComponent,
  ],
  templateUrl: './detalle-pedido.component.html',
})
export class DetallePedidoComponent implements OnInit {
  pedido: PedidoDetalle | null = null;
  cargando = false;
  cambiandoEstado = false;

  estadoSeleccionado = '';
  notaInterna = '';

  // Modales
  validarVisible = false;
  cobrarVisible = false;
  rechazarVisible = false;
  pagoSeleccionado: PagoItem | null = null;

  // ---------- Cheques ----------
  recibirChequeVisible = false;
  rechazarChequeVisible = false;
  descartarVisible = false;

  /**
   * ppa_id del cheque en edición. Se guarda el id y no el objeto porque
   * cada respuesta de la API reemplaza el PedidoDetalle entero: una
   * referencia al PagoItem viejo quedaría apuntando a datos muertos.
   */
  chequeEditandoId: number | null = null;
  formCheque!: FormGroup;
  guardandoCheque = false;

  motivoDescarte = '';
  descartando = false;

  /** Política configurada en Métodos de Pago; la usa el aviso del modal. */
  stockAlRecibir = true;

  // Generación de factura
  generandoFactura = false;

  // Reenvío de factura
  reenvioVisible = false;
  reenviando = false;
  canalReenvio: 'CORREO' | 'WHATSAPP' = 'CORREO';
  destinoReenvio = '';

  /** El envío por WhatsApp es asistido: se descarga el PDF y se abre el chat. */
  readonly whatsappDisponible = true;
  descargandoPdf = false;

  constructor(
    private service: PedidosService,
    private route: ActivatedRoute,
    private router: Router,
    private toastr: ToastrService,
    private fb: FormBuilder,
  ) {
    this.formCheque = this.fb.group({
      tipo: ['VISTA', [Validators.required]],
      banco: ['', [Validators.required, Validators.maxLength(80)]],
      nombre: ['', [Validators.required, Validators.maxLength(150)]],
      nrodoc: ['', [Validators.required, Validators.maxLength(20)]],
      nro: ['', [Validators.required, Validators.maxLength(20)]],
      fecha_cobro: [''],
    });
  }

  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    if (!id) {
      this.router.navigate(['/pedidos']);
      return;
    }

    this.cargarConfigCheque();
    this.cargar(id);
  }

  cargar(id: number): void {
    this.cargando = true;

    this.service.show(id).subscribe({
      next: (res) => {
        this.aplicar(res.data);
        this.cargando = false;
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo cargar el pedido', 'Error');
        this.cargando = false;
        this.router.navigate(['/pedidos']);
      },
    });
  }

  /** Punto único de actualización de la pantalla. */
  private aplicar(data: PedidoDetalle): void {
    this.pedido = data;
    this.notaInterna = data.nota_interna ?? '';
    this.estadoSeleccionado = '';
  }

  volver(): void {
    this.router.navigate(['/pedidos']);
  }

  // ---------- Pagos ----------

  abrirValidar(pago: PagoItem): void {
    this.pagoSeleccionado = pago;
    this.validarVisible = true;
  }

  abrirCobrar(pago: PagoItem): void {
    this.pagoSeleccionado = pago;
    this.cobrarVisible = true;
  }

  abrirRechazar(pago: PagoItem): void {
    this.pagoSeleccionado = pago;
    this.rechazarVisible = true;
  }

  onPagoActualizado(data: PedidoDetalle): void {
    this.aplicar(data);
    this.pagoSeleccionado = null;
  }

    // ---------- Cheques ----------

  /**
   * El aviso del modal de recepción cambia según dónde se descuenta el
   * stock, y eso sólo lo sabe la API. Un fallo acá no rompe la pantalla:
   * se asume el valor por defecto (RECEPCION).
   */
  private cargarConfigCheque(): void {
    this.service.configGestion().subscribe({
      next: (res) => { this.stockAlRecibir = res.cheque?.stock_al_recibir ?? true; },
      error: () => {},
    });
  }

  esCheque(pago: PagoItem): boolean {
    return !!pago.cheque;
  }

  /** Oculta la tarjeta COMPROBANTE cuando no hay nada que validar ahí. */
  get soloCheques(): boolean {
    const pago = this.pedido?.pago;
    return !!pago && pago.cantidad > 0 && pago.cheques_total === pago.cantidad;
  }

  /** Contador del encabezado: «1 de 2 cheques recibidos». */
  get contadorCheques(): string {
    const pago = this.pedido?.pago;
    if (!pago) return '';

    return pago.cheques_total === 1
      ? (pago.cheques_recibidos === 1 ? 'Cheque recibido' : 'Cheque en espera')
      : `${pago.cheques_recibidos} de ${pago.cheques_total} cheques recibidos`;
  }

  colorEstadoCheque(estado: string | null | undefined): string {
    switch (estado) {
      case 'ACREDITADO':   return 'success';
      case 'RECIBIDO':     return 'info';
      case 'EN_ESPERA':    return 'warning';
      case 'DESCARTADO':   return 'info';
      case 'NO_ENTREGADO': return 'danger';
      case 'RECHAZADO':    return 'danger';
      default:             return 'secondary';
    
    }
  }

  /**
   * Tiempo que le queda al cliente para acercar el papel.
   *
   * El «ya venció» lo decide `chq.vencido` en el backend, no esta cuenta:
   * de ese dato depende que el pedido se anule, y el reloj del navegador
   * del administrador puede estar corrido. Acá sólo se traduce a algo
   * legible para sucursal.
   */
  plazoRestante(chq: ChequePago): string {
    if (!chq.vence) return '';

    const restanteMs = new Date(chq.vence).getTime() - Date.now();
    if (restanteMs <= 0) return 'Plazo vencido';

    const horas = Math.floor(restanteMs / 3600000);
    const minutos = Math.floor((restanteMs % 3600000) / 60000);

    return horas > 0 ? `Quedan ${horas} h ${minutos} min` : `Quedan ${minutos} min`;
  }

  // ----- Edición en línea -----

  editando(pago: PagoItem): boolean {
    return this.chequeEditandoId === pago.ppa_id;
  }

  /**
   * [Editar] carga el formulario con lo que hay. En un cheque descartado
   * los campos vienen vacíos a propósito: es el estado en que el admin
   * tiene que cargar los datos del papel que trajo el cliente.
   */
  iniciarEdicion(pago: PagoItem): void {
    const chq = pago.cheque;
    if (!chq) return;

    this.formCheque.reset({
      tipo: chq.tipo ?? 'VISTA',
      banco: chq.banco ?? '',
      nombre: chq.nombre ?? '',
      nrodoc: chq.nrodoc ?? '',
      nro: chq.nro ?? '',
      fecha_cobro: this.aIsoFecha(chq.fecha_cobro),
    });

    this.chequeEditandoId = pago.ppa_id;
  }

  /** La API devuelve dd/mm/yyyy; <input type="date"> exige yyyy-mm-dd. */
  private aIsoFecha(fecha: string | null): string {
    if (!fecha) return '';

    const partes = fecha.split('/');
    return partes.length === 3 ? `${partes[2]}-${partes[1]}-${partes[0]}` : fecha;
  }

  cancelarEdicion(): void {
    this.chequeEditandoId = null;
    this.formCheque.reset({ tipo: 'VISTA', banco: '', nombre: '', nrodoc: '', nro: '', fecha_cobro: '' });
  }

  /** La fecha de cobro sólo existe en el diferido. */
  get chequeEsDiferido(): boolean {
    return this.formCheque.value.tipo === 'DIFERIDO';
  }

  get puedeGuardarCheque(): boolean {
    if (!this.formCheque.valid || this.guardandoCheque) return false;
    if (this.chequeEsDiferido && !this.formCheque.value.fecha_cobro) return false;
    return true;
  }

  errorCheque(campo: string): string | null {
    return this.formCheque.get(campo)?.errors?.['backend'] ?? null;
  }

  guardarCheque(pago: PagoItem): void {
    if (!this.pedido || !this.puedeGuardarCheque) {
      this.formCheque.markAllAsTouched();
      return;
    }

    this.guardandoCheque = true;

    this.service
      .actualizarCheque(this.pedido.ped_id, pago.ppa_id, {
        tipo: this.formCheque.value.tipo,
        banco: this.formCheque.value.banco.trim(),
        nombre: this.formCheque.value.nombre.trim(),
        nrodoc: this.formCheque.value.nrodoc.trim(),
        nro: this.formCheque.value.nro.trim(),
        fecha_cobro: this.chequeEsDiferido ? this.formCheque.value.fecha_cobro : null,
      })
      .subscribe({
        next: (res) => {
          this.toastr.success(res.message, 'Cheque actualizado');
          this.aplicar(res.data);
          this.guardandoCheque = false;
          this.cancelarEdicion();
        },
        error: (err) => {
          this.guardandoCheque = false;
          this.mostrarErrores(err, 'No se pudo actualizar el cheque', this.formCheque);
        },
      });
  }

  // ----- Descartar -----

  abrirDescartar(pago: PagoItem): void {
    this.pagoSeleccionado = pago;
    this.motivoDescarte = '';
    this.descartarVisible = true;
  }

  confirmarDescarte(): void {
    const pago = this.pagoSeleccionado;
    if (!this.pedido || !pago || this.descartando) return;

    this.descartando = true;

    this.service
      .descartarCheque(this.pedido.ped_id, pago.ppa_id, {
        confirma: true,
        motivo: this.motivoDescarte.trim() || null,
      })
      .subscribe({
        next: (res) => {
          this.toastr.success(res.message, 'Cheque descartado');
          this.aplicar(res.data);
          this.descartando = false;
          this.descartarVisible = false;

          // Queda listo para cargar los datos del papel que sí trajo.
          const actualizado = res.data.pago.items.find((p) => p.ppa_id === pago.ppa_id);
          if (actualizado) this.iniciarEdicion(actualizado);
        },
        error: (err) => {
          this.descartando = false;
          this.toastr.error(
            err?.error?.errors?.cheque?.[0] ?? err?.error?.message ?? 'No se pudo descartar el cheque',
            'Error',
          );
        },
      });
  }

  // ----- Modales -----

  abrirRecibirCheque(pago: PagoItem): void {
    this.cancelarEdicion();
    this.pagoSeleccionado = pago;
    this.recibirChequeVisible = true;
  }

  abrirRechazarCheque(pago: PagoItem): void {
    this.cancelarEdicion();
    this.pagoSeleccionado = pago;
    this.rechazarChequeVisible = true;
  }

  /** Vuelca los errores de validación de la API sobre el formulario. */
  private mostrarErrores(err: any, titulo: string, form: FormGroup): void {
    const errores = err?.error?.errors;

    if (errores) {
      Object.entries(errores).forEach(([campo, mensajes]) => {
        form.get(campo)?.setErrors({ backend: (mensajes as string[])[0] });
      });
      this.toastr.error((Object.values(errores)[0] as string[])[0], titulo);
      return;
    }

    this.toastr.error(err?.error?.message ?? titulo, 'Error');
  }

  /**
   * Las reglas de validar y cobrar las calcula la API (puede_validar /
   * puede_cobrar) y acá sólo se les suma el estado del pedido. La pantalla
   * repite la regla, no la define: si alguien llama la API por fuera del
   * panel, la que manda es la del service.
   *
   * Un pago cobrado no se vuelve a tocar desde acá: revertirlo exige
   * devolver stock y, como la factura ya está declarada, una nota de
   * crédito.
   */
  private pedidoOperable(): boolean {
    return !!this.pedido && this.pedido.estado_pedido.codigo !== 'CANCELADO';
  }

  puedeValidar(pago: PagoItem): boolean {
    return this.pedidoOperable() && pago.puede_validar;
  }

  /**
   * El cobro exige, además, que la factura exista: se aplica contra el
   * documento fiscal, no contra el pedido.
   */
  puedeCobrar(pago: PagoItem): boolean {
    return this.pedidoOperable()
      && pago.puede_cobrar
      && !!this.pedido?.pago.cobro_habilitado;
  }

  // ---------- Factura ----------

  /**
   * Por qué [GENERAR FACTURA] está deshabilitado. La API decide con
   * VentaService::validarHabilitacion(); acá sólo se explica, para que el
   * admin no quede mirando un botón gris sin saber qué le falta.
   */
  motivoFactura(): string {
    const pago = this.pedido?.pago;
    if (!pago || this.pedido?.facturacion?.puede_generar) return '';

    if (pago.comprobantes_pendientes > 0) {
      return pago.comprobantes_pendientes === 1
        ? 'Queda un comprobante sin validar.'
        : `Quedan ${pago.comprobantes_pendientes} comprobantes sin validar.`;
    }

    // Un cheque sin recibir bloquea la factura por el mismo motivo que un
    // comprobante sin validar: todavía no hay nada que respalde el documento.
    const chequesPendientes = pago.cheques_total - pago.cheques_recibidos;
    if (chequesPendientes > 0) {
      return chequesPendientes === 1
        ? 'Falta confirmar la recepción del cheque en la sucursal.'
        : `Faltan ${chequesPendientes} cheques por recibir en la sucursal.`;
    }

    return 'Este pedido tiene un pago en efectivo: la factura se genera cuando el pedido está LISTO.';
  }

  generarFactura(): void {
    if (!this.pedido || this.generandoFactura) return;

    this.generandoFactura = true;

    this.service.generarFactura(this.pedido.ped_id).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Factura generada');
        this.aplicar(res.data);
        this.generandoFactura = false;
      },
      error: (err) => {
        this.generandoFactura = false;
        const mensaje =
          err?.error?.errors?.factura?.[0] ??
          err?.error?.message ??
          'No se pudo generar la factura';
        this.toastr.error(mensaje, 'Error');
      },
    });
  }

  /** Rechazar es la otra salida de la tarjeta Comprobante. */
  puedeRechazar(pago: PagoItem): boolean {
    if (!this.pedidoOperable()) return false;
    // El cheque se rechaza desde su propia tarjeta, con su propio catálogo.
    if (pago.cheque) return false;
    if (!pago.metodo.requcom) return false;
    return pago.estado.codigo !== 'CONFIRMADO' && pago.estado.codigo !== 'RECHAZADO';
  }

  // ---------- Reenvío de factura ----------

  /** El canal que eligió el cliente en el checkout. */
  get canalDelCliente(): 'CORREO' | 'WHATSAPP' {
    return this.pedido?.facturacion?.recibe_por ?? 'CORREO';
  }

  abrirReenvio(): void {
    // Mientras WhatsApp no exista, se ofrece correo aunque el cliente haya elegido otro.
    this.canalReenvio = this.whatsappDisponible ? this.canalDelCliente : 'CORREO';
    this.destinoReenvio = this.destinoSugerido(this.canalReenvio);
    this.reenvioVisible = true;
  }

  cambiarCanalReenvio(): void {
    this.destinoReenvio = this.destinoSugerido(this.canalReenvio);
  }

  private destinoSugerido(canal: 'CORREO' | 'WHATSAPP'): string {
    const fac = this.pedido?.facturacion;
    return (canal === 'WHATSAPP' ? fac?.telefono : fac?.correo) ?? '';
  }

  confirmarReenvio(): void {
    if (!this.pedido || this.reenviando) return;

    this.reenviando = true;

    this.service
      .reenviarFactura(this.pedido.ped_id, this.canalReenvio, this.destinoReenvio.trim() || null)
      .subscribe({
        next: (res) => {
          this.toastr.success(res.message, 'Factura reenviada');
          this.aplicar(res.data);
          this.reenvioVisible = false;
          this.reenviando = false;
        },
        error: (err) => {
          this.toastr.error(err?.error?.message ?? 'No se pudo reenviar la factura', 'Error');
          this.reenviando = false;
        },
      });
  }

  // ---------- Envío asistido por WhatsApp ----------

  /**
   * Celular paraguayo en formato internacional y sin "+", que es lo que exige wa.me.
   * Acepta 0981123456, 0981 123 456, +595981123456, 981123456...
   * Devuelve null si no es un celular válido: es preferible avisar a abrir un chat equivocado.
   */
  private normalizarCelularPy(texto: string): string | null {
    let d = (texto ?? '').replace(/\D/g, '');

    if (d.startsWith('595')) d = d.slice(3);
    if (d.startsWith('0')) d = d.slice(1);

    return /^9\d{8}$/.test(d) ? `595${d}` : null;
  }

  get mensajeWhatsapp(): string {
    const fac = this.pedido?.facturacion;
    const razon = (fac?.razon_social ?? '').trim();
    const nombre = fac?.tipo_documento === 'CI' ? razon.split(/\s+/)[0] : razon;
    const saludo = nombre ? `Hola ${nombre}` : 'Hola';
    const empresa = fac?.factura?.empresa ? ` en ${fac.factura.empresa}` : '';

    return `${saludo}, te enviamos la factura N.º ${fac?.factura?.numero} de tu compra${empresa}. ¡Gracias por tu compra!`;
  }

  /** null si el teléfono no es un celular válido. */
  get whatsappUrl(): string | null {
    const numero = this.normalizarCelularPy(this.destinoReenvio);

    return numero ? `https://wa.me/${numero}?text=${encodeURIComponent(this.mensajeWhatsapp)}` : null;
  }

  abrirWhatsapp(): void {
    const url = this.whatsappUrl;
    if (!url) return;

    window.open(url, '_blank', 'noopener');
  }

  descargarPdfFactura(): void {
    const pedido = this.pedido;
    if (!pedido || this.descargandoPdf) return;

    this.descargandoPdf = true;

    this.service.kudeFactura(pedido.ped_id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const enlace = document.createElement('a');
        enlace.href = url;
        enlace.download = `${pedido.facturacion?.factura?.numero ?? 'factura'}.pdf`;
        enlace.click();
        window.URL.revokeObjectURL(url);
        this.descargandoPdf = false;
      },
      error: async (err) => {
        let mensaje = 'No se pudo descargar el PDF';

        // Con responseType blob, el error también llega como Blob.
        if (err?.error instanceof Blob) {
          try {
            mensaje = JSON.parse(await err.error.text())?.message ?? mensaje;
          } catch {}
        }

        this.toastr.error(mensaje, 'Error');
        this.descargandoPdf = false;
      },
    });
  }

  // ---------- Estado del pedido ----------

  get hayTransiciones(): boolean {
    return (this.pedido?.transiciones?.length ?? 0) > 0;
  }

  cambiarEstado(): void {
    if (!this.pedido || !this.estadoSeleccionado) return;

    this.cambiandoEstado = true;

    this.service.cambiarEstado(this.pedido.ped_id, this.estadoSeleccionado).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Estado actualizado');
        this.aplicar(res.data);
        this.cambiandoEstado = false;
      },
      error: (err) => {
        this.cambiandoEstado = false;
        const mensaje =
          err?.error?.errors?.estado?.[0] ??
          err?.error?.message ??
          'No se pudo cambiar el estado';
        this.toastr.error(mensaje, 'Error');
      },
    });
  }

  // TODO (instrucción futura): agregar nota interna y cancelar pedido.
  pendiente(accion: string): void {
    this.toastr.info(`"${accion}" se implementa en una instrucción posterior`, 'En construcción');
  }

  // ---------- Presentación ----------

  colorEstadoPago(codigo: string | null | undefined): string {
    switch (codigo) {
      case 'CONFIRMADO':     return 'success';
      case 'PARCIAL':        return 'info';
      case 'RECHAZADO':      return 'danger';
      case 'PAGO_VERIFICAR': return 'warning';
      default:               return 'secondary';
    }
  }

  colorEstadoPedido(codigo: string | null | undefined): string {
    switch (codigo) {
      case 'ENTREGADO':        return 'success';
      case 'LISTO':            return 'primary';
      case 'EN_PREPARACION':   return 'info';
      case 'CANCELADO':        return 'danger';
      case 'PENDIENTE_PEDIDO': return 'warning';
      default:                 return 'secondary';
    }
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }

    /**
   * Ver y descargar usan la misma descarga por blob: la ruta del
   * comprobante exige token JWT, así que un <a href> directo daría 401.
   */
  verComprobante(pago: PagoItem): void {
    this.obtenerComprobante(pago, (blobUrl) => {
      window.open(blobUrl, '_blank');
      setTimeout(() => window.URL.revokeObjectURL(blobUrl), 60000);
    });
  }

  descargarComprobante(pago: PagoItem): void {
    this.obtenerComprobante(pago, (blobUrl) => {
      const enlace = document.createElement('a');
      enlace.href = blobUrl;
      enlace.download = pago.comprobante?.archivo ?? 'comprobante';
      enlace.click();
      window.URL.revokeObjectURL(blobUrl);
    });
  }

  private obtenerComprobante(pago: PagoItem, accion: (blobUrl: string) => void): void {
    if (!this.pedido) return;

    this.service.comprobante(this.pedido.ped_id, pago.ppa_id).subscribe({
      next: (blob) => accion(window.URL.createObjectURL(blob)),
      error: () => this.toastr.error('No se pudo obtener el comprobante', 'Error'),
    });
  }
}