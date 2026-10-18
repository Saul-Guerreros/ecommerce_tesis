import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ReactiveFormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { switchMap } from 'rxjs';
import {
  EntregaCheckout, MedioRecepcion, ResumenCompra,
  TipoDocumentoFacturacion, TitularFacturacion,
} from '../../../core/models/checkout.model';
import { DatoFiscal } from '../../../core/models/dato-fiscal.model';
import { EntregaResumenCard, ProductoResumenCardItem } from '../../../core/models/resumen-compra.model';
import { CheckoutService } from '../../../core/services/checkout.service';
import { CarritoService } from '../../../core/services/carrito.service';
import { DatoFiscalClienteService } from '../../../core/services/dato-fiscal-cliente.service';
import { FormDatoFiscalComponent } from '../form-dato-fiscal/form-dato-fiscal.component';
import { ProductosCarritoCardComponent } from '../../../shared/components/productos-carrito-card/productos-carrito-card.component';
import { DatosEntregaCardComponent } from '../../../shared/components/datos-entrega-card/datos-entrega-card.component';

/**
 * Paso 2 del checkout: Facturación + [CONFIRMAR PEDIDO].
 *
 * Cambio de octubre 2026: este paso ya no pide el método de pago. Antes el
 * cliente pagaba acá y el pedido recién se creaba en el paso siguiente, de
 * modo que podía transferir por una unidad que otro cliente se llevaba
 * mientras tanto. Ahora el botón de este paso crea el pedido y reserva el
 * stock; el pago se declara después, en el Paso 3, sobre un pedido que ya
 * es suyo.
 *
 * Como consecuencia, este paso se volvió el último punto de revisión: por
 * eso muestra también los productos y la entrega.
 */
@Component({
  selector: 'app-paso-facturacion',
  standalone: true,
  imports: [
    CommonModule, CurrencyPipe, ReactiveFormsModule, RouterModule, FormDatoFiscalComponent,
    ProductosCarritoCardComponent, DatosEntregaCardComponent,
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

  /** Tarjeta "Productos en Carrito" de la columna lateral. */
  productos: ProductoResumenCardItem[] = [];

  datosFiscales: DatoFiscal[] = [];
  datoFiscalSeleccionado: DatoFiscal | null = null;

  cargando = true;
  confirmando = false;
  modalVisible = false;
  datoFiscalEditar: DatoFiscal | null = null;

  /**
   * Mensaje del backend cuando la reserva falla por falta de stock. Se
   * muestra fijo en la pantalla y no sólo como toast: es el caso que todo
   * el rediseño busca atajar y el cliente tiene que entender qué pasó.
   */
  errorStock: string | null = null;

  /**
   * true apenas el cliente intenta confirmar sin completar algo. A
   * partir de ahi los mensajes en rojo se recalculan solos a medida que
   * corrige cada campo (son getters, no un snapshot fijo).
   */
  intentoConfirmar = false;

  get medioInvalido(): boolean {
    return this.intentoConfirmar && !this.medioRecepcion;
  }

  get esRetiro(): boolean {
    return this.entrega?.modalidad === 'retiro';
  }

  get total(): number {
    return this.resumen.subtotal + this.resumen.costo_envio;
  }

  constructor(
    private checkoutService: CheckoutService,
    private carritoService: CarritoService,
    private datoFiscalService: DatoFiscalClienteService,
    private toastr: ToastrService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.cargar();
  }

  /**
   * Una sola llamada: al no existir más la sección de pago, este paso ya
   * no necesita combinar configFacturacion con configPago.
   */
  private cargar(): void {
    this.cargando = true;

    this.checkoutService.configFacturacion().subscribe({
      next: (resp) => {
        if (!resp.entrega) {
          // cargando queda en true a proposito: mantiene el spinner y evita
          // que la pagina se dibuje por un instante antes de redirigir.
          this.toastr.info('Tu checkout anterior ya no está disponible.', 'Checkout');
          this.router.navigate(['/carrito-de-compra'], { replaceUrl: true });
          return;
        }

        this.titular = resp.titular;
        this.datosFiscales = resp.datos_fiscales;
        this.entrega = resp.entrega;
        this.productos = resp.items.map((item) => ({
          imagen: item.publicacion_web?.imagen ?? null,
          nombre: item.publicacion_web?.pub_titulo ?? item.prd_descri,
          cantidad: item.car_cantid,
          total: item.car_total,
        }));
        this.resumen = resp.resumen;

        if (resp.facturacion) {
          this.tipoDocumento = resp.facturacion.tipo_documento;
          this.medioRecepcion = resp.facturacion.pfa_recibe;
        }

        // La predeterminada es siempre el respaldo, no solo cuando no
        // hay ninguna facturacion guardada todavia en este pedido. Si
        // la vez anterior el cliente habia elegido C.I. (o un RUC que
        // ya no existe), esto evita que la seccion RUC quede sin nada
        // marcado apenas se cambia a esa pestaña.
        const dfiIdGuardado = resp.facturacion?.tipo_documento === 'RUC'
          ? resp.facturacion.dfi_id
          : null;

        this.datoFiscalSeleccionado =
          (dfiIdGuardado && this.datosFiscales.find((d) => d.dfi_id === dfiIdGuardado))
          || this.datosFiscales.find((d) => d.dfi_principal)
          || this.datosFiscales[0]
          || null;

        this.cargando = false;
      },
      error: () => {
        this.cargando = false;
        this.toastr.error('No pudimos cargar tus datos de facturación.', 'Error');
        this.router.navigate(['/proceso-de-pago/entrega'], { replaceUrl: true });
      },
    });
  }

  // ---------- Datos fiscales ----------

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
        this.toastr.error(err.error?.message || 'No se pudo eliminar el dato fiscal.', 'Error');
      },
    });
  }

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

  // ---------- Entrega (tarjeta de resumen) ----------

  /**
   * EntregaCheckout (borrador del Paso 1) -> EntregaResumenCard, la forma
   * que espera <app-datos-entrega-card>. Es el único lugar del checkout
   * que necesita este adaptador: el Paso 3 y "Mis Pedidos" ya reciben la
   * entrega normalizada directamente desde el backend.
   */
  get entregaResumen(): EntregaResumenCard | null {
    if (!this.entrega) return null;

    return {
      modalidad: this.entrega.modalidad,
      receptor_nombre: `${this.entrega.rec_nombre} ${this.entrega.rec_apelli}`.trim(),
      receptor_telefono: this.entrega.rec_telef,
      receptor_documento: this.entrega.rec_nrodoc,
      suc_nombre: this.entrega.suc_nombre,
      suc_direccion: this.entrega.suc_direccion,
      suc_horario: this.entrega.suc_horario,
      calle: this.entrega.calle,
      nrocasa: this.entrega.nrocasa,
      barrio: this.entrega.barrio,
      ciudad: this.entrega.ciudad,
      departamento: this.entrega.departamento,
      referencia: this.entrega.referencia,
    };
  }

  // ---------- Confirmar ----------

  /**
   * Guarda la facturación y crea el pedido en una sola acción encadenada.
   *
   * Van juntas porque el backend exige la facturación para poder confirmar:
   * separarlas en dos botones dejaría al cliente ante un [Confirmar] que
   * falla si no apretó [Guardar] antes.
   */
  confirmar(): void {
    if (this.confirmando) return;

    this.intentoConfirmar = true;
    this.errorStock = null;

    if (this.tipoDocumento === 'RUC' && !this.datoFiscalSeleccionado) {
      this.toastr.error('Elegí o cargá un RUC.', 'Validación');
      return;
    }

    if (!this.medioRecepcion) {
      return; // el mensaje ya quedó visible en el formulario
    }

    const payload = this.tipoDocumento === 'CI'
      ? { tipo_documento: 'CI' as const, pfa_recibe: this.medioRecepcion }
      : { tipo_documento: 'RUC' as const, dfi_id: this.datoFiscalSeleccionado!.dfi_id, pfa_recibe: this.medioRecepcion };

    this.confirmando = true;

    this.checkoutService.guardarFacturacion(payload).pipe(
      switchMap(() => this.checkoutService.confirmarPedido()),
    ).subscribe({
      next: (resp) => {
        this.confirmando = false;

        // El backend ya vació el carrito al contado: se refleja acá para
        // que el ícono del header no siga mostrando productos fantasma.
        this.carritoService.reiniciar();
        this.checkoutService.reiniciar();

        this.toastr.success('Pedido creado. Ya reservamos tu stock.', 'Listo');

        // replaceUrl: el Paso 2 queda consumido. Volver con el botón del
        // navegador llevaría a un borrador que ya no existe.
        this.router.navigate(['/proceso-de-pago/pago', resp.data.ped_numero], { replaceUrl: true });
      },
      error: (e) => {
        this.confirmando = false;

        const errores = e.error?.errors as Record<string, string[]> | undefined;

        // Caso central del rediseño: el stock se agotó mientras el cliente
        // completaba el checkout. Se avisa antes de que pague nada.
        if (errores?.['stock']) {
          this.errorStock = errores['stock'][0];
          this.toastr.error(this.errorStock!, 'Sin stock');
          return;
        }

        const primero = errores ? Object.values(errores)[0]?.[0] : null;
        this.toastr.error(primero ?? e.error?.message ?? 'No pudimos confirmar tu pedido.', 'Error');
      },
    });
  }

  volver(): void {
    this.router.navigate(['/proceso-de-pago/entrega'], { replaceUrl: true });
  }

  irAlCarrito(): void {
    this.router.navigate(['/carrito-de-compra']);
  }
}