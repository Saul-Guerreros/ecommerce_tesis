import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ResultadoPago } from '../../core/models/checkout.model';
import { PagoPedidoService } from '../../core/services/pago-pedido.service';

/**
 * Pantalla final del checkout. Reemplaza a pedido-confirmado.
 *
 * Vive en su propia ruta y toma el número de pedido de la URL, por lo que
 * no depende de ningún borrador ni del carrito: es válida de forma
 * permanente y el cliente puede recargarla o volver con el botón del
 * navegador sin encontrarse una página muerta.
 *
 * Los tres casos del instructivo los clasifica el backend (campo 'caso'),
 * no esta pantalla: depende de qué métodos exigen comprobante, que es un
 * dato del catálogo metodo_pago.
 */
@Component({
  selector: 'app-pedido-resultado',
  standalone: true,
  imports: [CommonModule, CurrencyPipe],
  templateUrl: './pedido-resultado.component.html',
  styleUrl: './pedido-resultado.component.css',
})
export class PedidoResultadoComponent implements OnInit {
  numero = '';
  resultado!: ResultadoPago;
  cargando = true;

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

  private cargar(): void {
    this.cargando = true;

    this.servicio.resultado(this.numero).subscribe({
      next: (resp) => {
        this.resultado = resp.data;
        this.cargando = false;
      },
      error: (e) => {
        // El pedido existe pero todavía no tiene pago: el cliente llegó
        // acá por un link viejo o volvió atrás. Se lo manda a pagarlo.
        if (e.error?.errors?.['pedido']) {
          this.router.navigate(['/proceso-de-pago/pago', this.numero], { replaceUrl: true });
          return;
        }

        this.cargando = false;
        this.toastr.error(e.error?.message ?? 'No encontramos ese pedido.', 'Error');
        this.router.navigate(['/'], { replaceUrl: true });
      },
    });
  }

  get esRetiro(): boolean {
    return this.resultado?.entrega.modalidad === 'retiro';
  }

  /** Más de una transferencia: el instructivo pide detallarlas una por una. */
  get variosComprobantes(): boolean {
    return this.resultado.pagos.filter((p) => p.tiene_comprobante).length > 1;
  }

  get pagosConComprobante() {
    return this.resultado.pagos.filter((p) => p.tiene_comprobante);
  }

  ordinal(orden: number): string {
    const nombres = ['primer', 'segundo', 'tercer', 'cuarto', 'quinto',
      'sexto', 'séptimo', 'octavo', 'noveno', 'décimo'];

    return nombres[orden - 1] ?? `pago ${orden}`;
  }

  // ---------- Navegación ----------

  verMisPedidos(): void {
    this.router.navigate(['/mis-pedidos']);
  }

  volverAlCarrito(): void {
    this.router.navigate(['/carrito-de-compra']);
  }

  volverATienda(): void {
    this.router.navigate(['/']);
  }
}