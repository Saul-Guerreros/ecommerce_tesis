import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { CuentaPorPagar, CuotaPendiente } from '../models/cuenta-por-pagar.model';
import { CuentasPorPagarService } from '../service/cuentas-por-pagar.service';
import { FormPagoComponent } from '../form-pago/form-pago.component';

@Component({
  selector: 'app-detalle-cuotas-pagar',
  standalone: true,
  imports: [CommonModule, BadgeModule, ButtonModule, ModalModule, SpinnerModule, TableModule, FormPagoComponent],
  templateUrl: './detalle-cuotas-pagar.component.html',
})
export class DetalleCuotasPagarComponent implements OnChanges {
  @Input() visible = false;
  @Input() factura: CuentaPorPagar | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() pagoRegistrado = new EventEmitter<void>();

  cuotas: CuotaPendiente[] = [];
  cargando = false;
  anulandoId: number | null = null;

  pagoVisible = false;
  cuotaAPagar: CuotaPendiente | null = null;

  constructor(private service: CuentasPorPagarService, private toastr: ToastrService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.factura?.com_id) {
      this.cargar();
    }
  }

  private cargar(): void {
    this.cargando = true;
    this.service.getCuotas(this.factura!.com_id).subscribe({
      next: (res) => { this.cuotas = res; this.cargando = false; },
      error: () => {
        this.toastr.error('No se pudieron cargar las cuotas', 'Error');
        this.cargando = false;
      },
    });
  }

  abrirPago(c: CuotaPendiente): void {
    this.cuotaAPagar = c;
    this.pagoVisible = true;

    // Oculta este modal mientras se paga, para no apilar 2 c-modal a la vez.
    this.visible = false;
    this.visibleChange.emit(false);
  }

  onPagoCerrado(): void {
    // Se cierre con pago guardado o cancelado, siempre volvemos a mostrar la lista de cuotas.
    this.visible = true;
    this.visibleChange.emit(true);
  }


  onPagoGuardado(): void {
    this.cargar();               // refresca saldo/estado de las cuotas
    this.pagoRegistrado.emit();  // refresca la lista de facturas del padre
    this.onPagoCerrado();
  }

  anular(c: CuotaPendiente): void {
    if (!c.pago) return;

    const motivo = window.prompt(`Motivo de anulación del pago de la cuota N° ${c.cuo_nrocuo}:`);
    if (motivo === null) return; // el usuario canceló

    if (motivo.trim().length < 5) {
      this.toastr.error('Indique un motivo de al menos 5 caracteres', 'Falta motivo');
      return;
    }

    this.anulandoId = c.pago.pgo_id;
    this.service.anularPago(c.pago.pgo_id, motivo.trim()).subscribe({
      next: () => {
        this.toastr.success('Pago anulado', 'Éxito');
        this.anulandoId = null;
        this.cargar();
        this.pagoRegistrado.emit();
      },
      error: (err) => {
        this.anulandoId = null;
        this.toastr.error(err?.error?.message ?? 'No se pudo anular el pago', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}