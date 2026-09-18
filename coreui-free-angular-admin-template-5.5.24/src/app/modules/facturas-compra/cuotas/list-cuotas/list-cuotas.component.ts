import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Compra } from '../../models/compra.model';
import { CuotaCompra } from '../models/cuota-compra.model';
import { CuotasService } from '../service/cuotas.service';

@Component({
  selector: 'app-list-cuotas',
  standalone: true,
  imports: [CommonModule, BadgeModule, ButtonModule, ModalModule, SpinnerModule, TableModule],
  templateUrl: './list-cuotas.component.html',
})
export class ListCuotasComponent implements OnChanges {
  @Input() visible = false;
  @Input() compra: Compra | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();

  cuotas: CuotaCompra[] = [];
  cargando = false;
  
  constructor(private service: CuotasService, private toastr: ToastrService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.compra?.com_id) {
      this.cargar();
    }
  }

  private cargar(): void {
    this.cargando = true;
    this.service.getAll(this.compra!.com_id!).subscribe({
      next: (res) => { this.cuotas = res; this.cargando = false; },
      error: () => {
        this.toastr.error('No se pudieron cargar las cuotas', 'Error');
        this.cargando = false;
      },
    });
  }

  get totalPendiente(): number {
    return this.cuotas
      .filter((c) => c.cuo_estado === 0)
      .reduce((s, c) => s + Number(c.cuo_importe), 0);
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}