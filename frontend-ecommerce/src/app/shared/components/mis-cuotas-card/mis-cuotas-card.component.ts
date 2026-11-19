import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, Input, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { MisCuotasRespuesta } from '../../../core/models/cuota-cliente.model';
import { MisCuotasService } from '../../../core/services/mis-cuotas.service';

/**
 * Puente entre el pedido y la sección "Mis Cuotas".
 *
 * Antes esta tarjeta era el cronograma completo con su formulario de
 * pago. Se vació a propósito: un pedido y una cuota son cosas distintas,
 * y el cronograma vive ahora en /mis-cuotas/:numero. Acá sólo queda el
 * aviso de que las cuotas existen (o todavía no) y el acceso.
 */
@Component({
  selector: 'app-mis-cuotas-card',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, RouterModule],
  templateUrl: './mis-cuotas-card.component.html',
})
export class MisCuotasCardComponent implements OnInit {
  @Input({ required: true }) numeroPedido = '';

  datos: MisCuotasRespuesta | null = null;
  cargando = true;

  constructor(private service: MisCuotasService) {}

  ngOnInit(): void {
    this.service.getCuotas(this.numeroPedido).subscribe({
      next: (res) => {
        this.datos = res;
        this.cargando = false;
      },
      // Silencioso: es una tarjeta informativa dentro del detalle del
      // pedido, no la pantalla de cuotas. Un toast de error acá sólo
      // asustaría sobre algo que el cliente no pidió ver.
      error: () => {
        this.cargando = false;
      },
    });
  }

  get pendientes(): number {
    const resumen = this.datos?.resumen;
    return resumen ? resumen.total_cuotas - resumen.cobradas : 0;
  }
}