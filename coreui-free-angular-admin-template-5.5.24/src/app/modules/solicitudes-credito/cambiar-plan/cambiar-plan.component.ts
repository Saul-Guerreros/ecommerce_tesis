import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import {
  AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule,
} from '@coreui/angular';

import {
  DetalleSolicitudLinea, PlanDisponible, SolicitudCreditoDetalle,
} from '../models/solicitud-credito-detalle.model';
import { SolicitudesCreditoService } from '../service/solicitudes-credito.service';

/**
 * Modal «Cambiar plan de cuotas» de una línea de la compra.
 *
 * Los planes los trae el backend contra scd_preuni (precio congelado) y
 * no contra el precio actual del catálogo: cambiar el plazo no puede
 * cambiar el precio que el cliente ya vio.
 *
 * El componente NO calcula montos: muestra los que devuelve el endpoint,
 * que salen de los mismos métodos de ConfiguracionCuota que usó el
 * carrito. Recalcular acá sería una segunda fórmula que se desincroniza.
 */
@Component({
  selector: 'app-cambiar-plan',
  standalone: true,
  imports: [
    CommonModule, FormsModule, ModalModule, ButtonModule,
    FormModule, AlertModule, SpinnerModule, TableModule,
  ],
  templateUrl: './cambiar-plan.component.html',
})
export class CambiarPlanComponent implements OnChanges {
  @Input() visible = false;
  @Input() solicitudId: number | null = null;
  @Input() linea: DetalleSolicitudLinea | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<SolicitudCreditoDetalle>();

  planes: PlanDisponible[] = [];
  seleccion: number | null = null;

  cargando = false;
  guardando = false;
  errorPlanes: string | null = null;

  constructor(
    private service: SolicitudesCreditoService,
    private toastr: ToastrService,
  ) {}

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['visible'] || changes['linea']) && this.visible && this.linea && this.solicitudId) {
      this.seleccion = this.linea.scd_numcuo;
      this.cargarPlanes();
    }
  }

  private cargarPlanes(): void {
    if (!this.solicitudId || !this.linea) return;

    this.cargando = true;
    this.errorPlanes = null;
    this.planes = [];

    this.service.planes(this.solicitudId, this.linea.scd_id).subscribe({
      next: (res) => {
        this.planes = res.data;
        this.cargando = false;

        if (this.planes.length === 0) {
          this.errorPlanes = 'Este producto no tiene planes de financiación activos.';
        }
      },
      error: (err) => {
        this.cargando = false;
        this.errorPlanes = err?.error?.message ?? 'No se pudieron cargar los planes.';
      },
    });
  }

  get hayCambio(): boolean {
        return this.seleccion !== null && this.seleccion !== this.linea?.scd_numcuo;
  }

  get puedeGuardar(): boolean {
    return this.hayCambio && !this.guardando && !this.cargando;
  }

  get planElegido(): PlanDisponible | null {
    return this.planes.find((p) => p.cantidad === this.seleccion) ?? null;
  }

  confirmar(): void {
    if (!this.solicitudId || !this.linea || !this.puedeGuardar) return;

    this.guardando = true;

    this.service.cambiarPlan(this.solicitudId, this.linea.scd_id, this.seleccion!).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Plan actualizado');
        this.guardado.emit(res.data);
        this.guardando = false;
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;

        // 409: la solicitud ya fue resuelta por otro administrador.
        if (err?.status === 409) {
          this.toastr.error(err?.error?.message ?? 'La solicitud ya fue resuelta.', 'No se pudo cambiar');
          this.cerrar();
          return;
        }

        const mensaje =
          err?.error?.errors?.cuotas?.[0] ??
          err?.error?.message ??
          'No se pudo cambiar el plan';

        this.toastr.error(mensaje, 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}