import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import {
  AlertModule, ButtonModule, FormModule, ModalModule, SpinnerModule,
} from '@coreui/angular';

import { MotivoRechazo, PagoItem, PedidoDetalle } from '../models/pedido-detalle.model';
import { PedidosService } from '../service/pedidos.service';

/**
 * Modal «Rechazar cheque».
 *
 * A diferencia del rechazo de un comprobante, acá no hay segunda
 * oportunidad: el cliente no tiene en «Mis pedidos» ninguna pantalla para
 * declarar otro cheque, así que el rechazo cancela el pedido y libera la
 * reserva de stock. La casilla de confirmación existe por eso, y el texto
 * lo dice sin eufemismos.
 *
 * Los motivos los sirve la API ya filtrados por ámbito: acá no se
 * reconstruye esa regla.
 */
@Component({
  selector: 'app-rechazar-cheque',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, ModalModule, ButtonModule,
    FormModule, AlertModule, SpinnerModule,
  ],
  templateUrl: './rechazar-cheque.component.html',
})
export class RechazarChequeComponent implements OnChanges {
  @Input() visible = false;
  @Input() pedido: PedidoDetalle | null = null;
  @Input() pago: PagoItem | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<PedidoDetalle>();

  form!: FormGroup;
  motivos: MotivoRechazo[] = [];
  guardando = false;
  cargandoMotivos = false;

  constructor(
    private fb: FormBuilder,
    private service: PedidosService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      motivo_id: [null, [Validators.required]],
      detalle: [''],
      confirma: [false, [Validators.requiredTrue]],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['visible'] || changes['pago']) && this.visible && this.pago) {
      this.form.reset({ motivo_id: null, detalle: '', confirma: false });
      this.cargarMotivos();
    }
  }

  private cargarMotivos(): void {
    if (this.motivos.length || this.cargandoMotivos) return;

    this.cargandoMotivos = true;

    this.service.configGestion().subscribe({
      next: (res) => {
        this.motivos = res.motivos_rechazo_cheque ?? [];
        this.cargandoMotivos = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar los motivos de rechazo', 'Error');
        this.cargandoMotivos = false;
      },
    });
  }

  get motivoElegido(): MotivoRechazo | null {
    const id = Number(this.form.value.motivo_id);
    return this.motivos.find((m) => m.mrp_id === id) ?? null;
  }

  /**
   * Algunos motivos no se entienden solos: «Datos no coinciden» obliga a
   * decir cuáles. La API aplica la misma regla; acá sólo se anticipa para
   * no mandar un request que va a fallar.
   */
  get detalleObligatorio(): boolean {
    return this.motivoElegido?.mrp_requdet ?? false;
  }

  get puedeGuardar(): boolean {
    if (!this.form.valid || this.guardando) return false;
    if (this.detalleObligatorio && !String(this.form.value.detalle ?? '').trim()) return false;
    return true;
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  rechazar(): void {
    if (!this.pedido || !this.pago || !this.puedeGuardar) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    this.service
      .rechazarCheque(this.pedido.ped_id, this.pago.ppa_id, {
        motivo_id: Number(this.form.value.motivo_id),
        detalle: this.form.value.detalle || null,
        confirma: true,
      })
      .subscribe({
        next: (res) => {
          this.toastr.success(res.message, 'Cheque rechazado');
          this.guardado.emit(res.data);
          this.guardando = false;
          this.cerrar();
        },
        error: (err) => {
          this.guardando = false;

          const errores = err?.error?.errors;
          if (errores) {
            Object.entries(errores).forEach(([campo, mensajes]) => {
              this.form.get(campo)?.setErrors({ backend: (mensajes as string[])[0] });
            });
            this.toastr.error(
              (Object.values(errores)[0] as string[])[0],
              'No se pudo rechazar',
            );
            return;
          }

          this.toastr.error(err?.error?.message ?? 'No se pudo rechazar el cheque', 'Error');
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