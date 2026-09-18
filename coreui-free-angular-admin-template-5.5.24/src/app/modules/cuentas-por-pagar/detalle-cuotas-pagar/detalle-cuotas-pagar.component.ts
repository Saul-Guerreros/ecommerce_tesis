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
  imports: [CommonModule, BadgeModule, ButtonModule, ModalModule, SpinnerModule, TableModule,FormPagoComponent ],
  templateUrl: './detalle-cuotas-pagar.component.html',
})

export class DetalleCuotasPagarComponent implements OnChanges {
  @Input() visible = false;
  @Input() factura: CuentaPorPagar | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() pagoRegistrado = new EventEmitter<void>();

  cuotas: CuotaPendiente[] = [];
  cargando = false;
  pagando: number | null = null;

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

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  formPagoVisible = false;
  cuotaAPagar: CuotaPendiente | null = null;

  abrirPago(cuota: CuotaPendiente): void {
    this.cuotaAPagar = cuota;
    this.formPagoVisible = true;

    // Oculta este modal mientras se paga, para no apilar 2 c-modal a la vez.
    this.visible = false;
    this.visibleChange.emit(false);
  }

  // Reemplaza el binding directo (visibleChange)="formPagoVisible = $event"
  // del HTML: ahora, sea que el form se cerro por Cancelar, por click en el
  // backdrop, o por Guardar exitoso, siempre pasa por aca y reabre este modal.
  onFormPagoVisibleChange(visible: boolean): void {
    const estabaAbierto = this.formPagoVisible;
    this.formPagoVisible = visible;

    // Solo se reabre Cuotas si el formulario de pago estaba REALMENTE abierto
    // y se acaba de cerrar. c-modal emite visibleChange(false) tambien al
    // crearse, antes de cualquier interaccion: sin esta guarda, esa emision
    // inicial abre el modal de Cuotas solo, sin factura seleccionada.
    if (!visible && estabaAbierto) {
      this.visible = true;
      this.visibleChange.emit(true);
    }
  }

  onPagoGuardado(): void {
    this.cargar();               // refresca saldo/estado de las cuotas
    this.pagoRegistrado.emit();  // refresca la lista de facturas del padre
  }
}