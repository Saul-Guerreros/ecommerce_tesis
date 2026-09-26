import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Compra } from '../../models/compra.model';
import { DetNotaCreditoResumen, MotivoNotaCredito } from '../models/nota-credito.model';
import { NotasCreditoService } from '../service/notas-credito.service';

@Component({
  selector: 'app-list-nota-credito',
  standalone: true,
  imports: [CommonModule, ButtonModule, ModalModule, SpinnerModule, TableModule],
  templateUrl: './list-nota-credito.component.html',
})
export class ListNotaCreditoComponent implements OnChanges {
  @Input() visible = false;
  @Input() compra: Compra | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();

  notas: { 
    ncr_id?: number; 
    ncr_numero: string;
    ncr_fecha: string;
    motivo?: MotivoNotaCredito | null; 
    ncr_total?: number
    detalle: DetNotaCreditoResumen[] 
  }[] = [];

  cargando = false;

  constructor(private service: NotasCreditoService, private toastr: ToastrService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.compra?.com_id) {
      this.cargar();
    }
  }

  private cargar(): void {
    this.cargando = true;
    this.service.getAll(this.compra!.com_id!).subscribe({
      next: (res) => { this.notas = res as any; this.cargando = false; },
      error: () => {
        this.toastr.error('No se pudo cargar el historial de Notas de Crédito', 'Error');
        this.cargando = false;
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}