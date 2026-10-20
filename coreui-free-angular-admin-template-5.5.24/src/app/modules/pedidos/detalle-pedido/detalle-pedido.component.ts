import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  AlertModule, BadgeModule, ButtonModule, CardModule, FormModule,
  ProgressModule, SpinnerModule, TableModule, TooltipModule,
} from '@coreui/angular';

import { PagoItem, PedidoDetalle } from '../models/pedido-detalle.model';
import { PedidosService } from '../service/pedidos.service';
import { ConfirmarPagoComponent } from '../confirmar-pago/confirmar-pago.component';
import { RechazarPagoComponent } from '../rechazar-pago/rechazar-pago.component';

import { HttpClient } from '@angular/common/http';

/**
 * Pantalla de Gestión del Pedido.
 *
 * Todas las respuestas de confirmar/rechazar/cambiar estado devuelven el
 * pedido completo, así que la pantalla se refresca con ese payload en
 * lugar de volver a pedir el detalle. Evita un segundo request y, sobre
 * todo, evita que la pantalla quede un instante mostrando datos viejos.
 */
@Component({
  selector: 'app-detalle-pedido',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule, AlertModule, ProgressModule,
    TooltipModule, ConfirmarPagoComponent, RechazarPagoComponent,
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
  confirmarVisible = false;
  rechazarVisible = false;
  pagoSeleccionado: PagoItem | null = null;

constructor(
  private service: PedidosService,
  private route: ActivatedRoute,
  private router: Router,
  private toastr: ToastrService,

) {}

  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    if (!id) {
      this.router.navigate(['/pedidos']);
      return;
    }

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

  abrirConfirmar(pago: PagoItem): void {
    this.pagoSeleccionado = pago;
    this.confirmarVisible = true;
  }

  abrirRechazar(pago: PagoItem): void {
    this.pagoSeleccionado = pago;
    this.rechazarVisible = true;
  }

  onPagoActualizado(data: PedidoDetalle): void {
    this.aplicar(data);
    this.pagoSeleccionado = null;
  }

  /**
   * Un pago confirmado no se vuelve a tocar desde acá: revertirlo exige
   * devolver stock y, si hubo factura, una nota de crédito.
   */
  puedeValidar(pago: PagoItem): boolean {
    if (!this.pedido) return false;
    if (this.pedido.estado_pedido.codigo === 'CANCELADO') return false;
    return pago.estado.codigo !== 'CONFIRMADO';
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
      case 'CONFIRMADO': return 'success';
      case 'PARCIAL':    return 'info';
      case 'RECHAZADO':     return 'danger';
      case 'PAGO_VERIFICAR': return 'warning';
      default:           return 'secondary';
    }
  }

  colorEstadoPedido(codigo: string | null | undefined): string {
    switch (codigo) {
      case 'ENTREGADO':      return 'success';
      case 'LISTO':          return 'primary';
      case 'EN_PREPARACION': return 'info';
      case 'CANCELADO':      return 'danger';
      case 'PENDIENTE_PEDIDO': return 'warning';
      default:               return 'secondary';
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