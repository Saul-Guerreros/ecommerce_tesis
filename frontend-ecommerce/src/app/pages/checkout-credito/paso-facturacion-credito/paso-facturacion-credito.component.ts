import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { MedioRecepcion, TipoDocumentoFacturacion } from '../../../core/models/checkout.model';
import { CheckoutCreditoContexto, ConfirmarCheckoutCreditoRequest } from '../../../core/models/credito.model';
import { DatoFiscal } from '../../../core/models/dato-fiscal.model';
import { CreditoService } from '../../../core/services/credito.service';
import { DatosEntregaCardComponent } from '../../../shared/components/datos-entrega-card/datos-entrega-card.component';
import { ProductosCarritoCardComponent } from '../../../shared/components/productos-carrito-card/productos-carrito-card.component';
import { FormDatoFiscalComponent } from '../../checkout/form-dato-fiscal/form-dato-fiscal.component';
import { ModalCondicionesCompraComponent } from '../../checkout/modal-condiciones-compra/modal-condiciones-compra.component';

/**
 * Paso 2 del checkout a crédito: Facturación + [CONFIRMAR PEDIDO].
 *
 * Mismo diseño que el Paso 2 del checkout al contado. Lo que cambia es la
 * fuente (la solicitud aceptada, no el carrito) y el resultado: se crea un
 * pedido a crédito que hereda la reserva de stock de la solicitud.
 */
@Component({
  selector: 'app-paso-facturacion-credito',
  standalone: true,
  imports: [
    CommonModule, CurrencyPipe, FormsModule, RouterModule,
    FormDatoFiscalComponent, ProductosCarritoCardComponent,
    DatosEntregaCardComponent, ModalCondicionesCompraComponent,
  ],
  templateUrl: './paso-facturacion-credito.component.html',
  styleUrl: '../../checkout/paso-facturacion/paso-facturacion.component.css',
})
export class PasoFacturacionCreditoComponent implements OnInit {
  ctx: CheckoutCreditoContexto | null = null;

  tipoDocumento: TipoDocumentoFacturacion = 'CI';
  medioRecepcion: MedioRecepcion | null = null;

  datosFiscales: DatoFiscal[] = [];
  datoFiscalSeleccionado: DatoFiscal | null = null;
  datoFiscalEditar: DatoFiscal | null = null;
  modalVisible = false;

  cargando = true;
  confirmando = false;
  intentoConfirmar = false;

  aceptaCondiciones = false;
  modalCondicionesVisible = false;

  /** Error de negocio del backend (stock, plazo vencido) que se muestra fijo en pantalla. */
  errorNegocio: string | null = null;

  private idSolicitud = 0;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private creditoService: CreditoService,
    private toastr: ToastrService,
  ) {}

  get medioInvalido(): boolean {
    return this.intentoConfirmar && !this.medioRecepcion;
  }

  ngOnInit(): void {
    this.idSolicitud = Number(this.route.parent?.snapshot.paramMap.get('id'));

    this.creditoService.checkoutContexto(this.idSolicitud).subscribe({
      next: (resp) => {
        this.ctx = resp.data;
        this.datosFiscales = resp.data.datos_fiscales;
        this.datoFiscalSeleccionado =
          this.datosFiscales.find((d) => d.dfi_principal) ?? this.datosFiscales[0] ?? null;
        this.cargando = false;
      },
      error: (err) => {
        this.toastr.warning(err.error?.message ?? 'No pudimos abrir el checkout de esta solicitud.', 'Checkout');
        this.router.navigate(['/mis-solicitudes-credito'], { replaceUrl: true });
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

  abrirCondiciones(event: Event): void {
    event.preventDefault();
    this.modalCondicionesVisible = true;
  }

  // ---------- Confirmar ----------

  confirmar(): void {
    if (this.confirmando || !this.aceptaCondiciones) return;

    this.intentoConfirmar = true;
    this.errorNegocio = null;

    if (this.tipoDocumento === 'RUC' && !this.datoFiscalSeleccionado) {
      this.toastr.error('Elegí o cargá un RUC.', 'Validación');
      return;
    }

    if (!this.medioRecepcion) {
      return; // el mensaje ya quedó visible en el formulario
    }

    const payload: ConfirmarCheckoutCreditoRequest = {
      tipo_documento: this.tipoDocumento,
      pfa_recibe: this.medioRecepcion,
      acepta_condiciones: this.aceptaCondiciones,
      ...(this.tipoDocumento === 'RUC' ? { dfi_id: this.datoFiscalSeleccionado!.dfi_id } : {}),
    };

    this.confirmando = true;

    this.creditoService.confirmarCheckout(this.idSolicitud, payload).subscribe({
      next: (resp) => {
        this.confirmando = false;
        this.toastr.success(resp.message, 'Listo');

        // replaceUrl: el checkout queda consumido; volver con el botón del
        // navegador llevaría a una solicitud que ya generó su pedido.
        this.router.navigate(['/mis-pedidos', resp.data.ped_numero], { replaceUrl: true });
      },
      error: (e) => {
        this.confirmando = false;

        // 409: venció el plazo, ya se generó el pedido o la reserva cayó.
        // Se explica y se vuelve al detalle, que muestra el estado real.
        if (e.status === 409) {
          this.toastr.warning(e.error?.message ?? 'La solicitud cambió.', 'Checkout');
          this.router.navigate(['/mis-solicitudes-credito', this.idSolicitud], { replaceUrl: true });
          return;
        }

        const errores = e.error?.errors as Record<string, string[]> | undefined;

        if (errores?.['stock']) {
          this.errorNegocio = errores['stock'][0];
          return;
        }

        const primero = errores ? Object.values(errores)[0]?.[0] : null;
        this.toastr.error(primero ?? e.error?.message ?? 'No pudimos confirmar tu pedido.', 'Error');
      },
    });
  }

  volver(): void {
    this.router.navigate(['/checkout-credito', this.idSolicitud, 'entrega'], { replaceUrl: true });
  }
}