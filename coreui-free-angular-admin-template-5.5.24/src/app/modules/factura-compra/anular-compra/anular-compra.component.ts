import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import { CompraCab } from '../models/compra.model';
import { ComprasService } from '../service/compras.service';

@Component({
  selector: 'app-anular-compra',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule, SpinnerModule],
  templateUrl: './anular-compra.component.html',
})
export class AnularCompraComponent {
  @Input() visible = false;
  @Input() compra: CompraCab | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() anulada = new EventEmitter<void>();

  anulando = false;

  constructor(private service: ComprasService, private toastr: ToastrService) {}

  confirmar(): void {
    if (!this.compra?.com_id) return;

    this.anulando = true;
    this.service.anular(this.compra.com_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Compra anulada', 'Éxito');
        this.anulando = false;
        this.anulada.emit();
        this.cerrar();
      },
      error: (err) => {
        this.anulando = false;
        // 409: el backend bloqueo la anulacion porque hay movimientos de
        // stock posteriores (otra compra o ajuste ya uso ese CPP).
        this.toastr.error(err?.error?.message ?? 'No se pudo anular la compra', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}