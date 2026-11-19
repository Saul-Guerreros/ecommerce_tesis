import { CommonModule, CurrencyPipe, DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { CompraConCuotas } from '../../core/models/cuota-cliente.model';
import { MisCuotasService } from '../../core/services/mis-cuotas.service';

/**
 * "Mis Cuotas": sección propia, hermana de "Mis Pedidos" y de "Mis
 * solicitudes de crédito".
 *
 * Lista una fila por compra a crédito ya facturada. Un pedido a crédito
 * sin factura no aparece acá: hasta que el admin la genera, la deuda no
 * existe y el cliente sólo ve el aviso dentro del pedido.
 */
@Component({
  selector: 'app-mis-cuotas',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, DatePipe, RouterModule],
  templateUrl: './mis-cuotas.component.html',
  styleUrl: './mis-cuotas.component.css',
})
export class MisCuotasComponent implements OnInit {
  compras: CompraConCuotas[] = [];
  cargando = true;

  constructor(
    private servicio: MisCuotasService,
    private router: Router,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.servicio.listar().subscribe({
      next: (resp) => {
        this.compras = resp.data ?? [];
        this.cargando = false;
      },
      error: () => {
        this.cargando = false;
        this.toastr.error('No pudimos cargar tus cuotas.', 'Error');
      },
    });
  }

  verDetalle(numero: string): void {
    this.router.navigate(['/mis-cuotas', numero]);
  }

  pendientes(c: CompraConCuotas): number {
    return c.total_cuotas - c.cobradas;
  }

  /** Verde cuando ya no debe nada: el estado que el cliente busca de un vistazo. */
  saldada(c: CompraConCuotas): boolean {
    return c.saldo <= 0;
  }
}