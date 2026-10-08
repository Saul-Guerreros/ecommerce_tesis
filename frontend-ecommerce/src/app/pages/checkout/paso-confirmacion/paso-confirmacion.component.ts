import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  ConfigConfirmacionResponse, EntregaCheckout, FacturacionCheckout,
  PagoCheckout, ResumenConfirmacion,
} from '../../../core/models/checkout.model';
import { CarritoItem } from '../../../core/models/carrito.model';
import { CheckoutService } from '../../../core/services/checkout.service';
import { CarritoService } from '../../../core/services/carrito.service';
import { URL_BACKEND } from '../../../config/config';

@Component({
  selector: 'app-paso-confirmacion',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, RouterModule],
  templateUrl: './paso-confirmacion.component.html',
  styleUrl: './paso-confirmacion.component.css',
})
export class PasoConfirmacionComponent implements OnInit {
  items: CarritoItem[] = [];
  entrega!: EntregaCheckout;
  facturacion!: FacturacionCheckout;
  pago!: PagoCheckout;
  resumen!: ResumenConfirmacion;

  cargando = true;
  confirmando = false;

  constructor(
    private checkoutService: CheckoutService,
    private carritoService: CarritoService,
    private toastr: ToastrService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.cargar();
  }

  private cargar(): void {
    this.cargando = true;
    this.checkoutService.configConfirmacion().subscribe({
      next: (resp: ConfigConfirmacionResponse) => {
        this.items = resp.items;
        this.entrega = resp.entrega;
        this.facturacion = resp.facturacion;
        this.pago = resp.pago;
        this.resumen = resp.resumen;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastr.error(err.error?.message ?? 'No pudimos cargar el resumen de tu pedido.', 'Error');
        this.router.navigate(['/carrito-de-compra'], { replaceUrl: true });
      },
    });
  }

  get esRetiro(): boolean {
    return this.entrega?.modalidad === 'retiro';
  }

  private static readonly NOMBRES: Record<string, string> = {
    TRANSFERENCIA: 'Transferencia bancaria',
    BILLETERA_MOVIL: 'Tigo Money',
    CONTRAENTREGA: 'Efectivo (contra entrega)',
  };

  get esPagoDividido(): boolean {
    return this.pago?.modo === 'dividido';
  }

  get metodoLabel(): string {
    return this.nombreMetodo(this.pago?.mpa_codigo) || (this.pago?.mpa_descri ?? '');
  }

  nombreMetodo(codigo: string | null | undefined): string {
    if (!codigo) return '';
    return PasoConfirmacionComponent.NOMBRES[codigo] ?? codigo;
  }

  titulo(item: CarritoItem): string {
    return item.publicacion_web?.pub_titulo ?? item.prd_descri;
  }

  resolverImagen(ruta: string | null | undefined): string {
    if (!ruta) return 'assets/images/products/placeholder.png';
    if (ruta.startsWith('http')) return ruta;
    return URL_BACKEND.replace(/\/$/, '') + '/' + ruta.replace(/^\//, '');
  }

  volver(): void {
    this.router.navigate(['/proceso-de-pago/facturacion'], { replaceUrl: true });
  }

  confirmar(): void {
    if (this.confirmando) return;
    this.confirmando = true;

    this.checkoutService.confirmarPedido().subscribe({
      next: (resp) => {
        this.confirmando = false;
        this.carritoService.reiniciar();
        this.checkoutService.reiniciar();

        // replaceUrl: la entrada del checkout queda consumida y en su lugar
        // queda la pantalla de pedido confirmado, que es una pagina valida
        // de forma permanente (no depende del borrador ya borrado).
        this.router.navigate(['/pedido-confirmado', resp.data.ped_numero], { replaceUrl: true });
      },
      error: (err) => {
        this.confirmando = false;
        this.toastr.error(err.error?.message ?? 'No pudimos confirmar tu pedido.', 'Error');
      },
    });
  }

}