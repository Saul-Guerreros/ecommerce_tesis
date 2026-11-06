import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, ButtonModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { ClienteService } from '../service/cliente.service';
import { Cliente, CodigoVinculacion } from '../models/cliente.model';

/** Código de vinculación para que el cliente cree su cuenta de la tienda sobre su ficha. */
@Component({
  selector: 'app-cliente-codigo',
  standalone: true,
  imports: [CommonModule, ModalModule, ButtonModule, AlertModule, SpinnerModule],
  templateUrl: './cliente-codigo.component.html',
})
export class ClienteCodigoComponent implements OnChanges {

  @Input() visible = false;
  @Input() cliente: Cliente | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();

  cargando = false;
  confirmando = false;
  vinculo: CodigoVinculacion | null = null;

  constructor(
    private clienteService: ClienteService,
    private toastr: ToastrService,
  ) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (!changes['visible']) { return; }

    if (this.visible && this.cliente) {
      this.cargar(false);
    } else if (!this.visible) {
      this.vinculo = null;
      this.confirmando = false;
    }
  }

  private cargar(nuevo: boolean): void {
    if (!this.cliente) { return; }
    this.cargando = true;

    this.clienteService.codigoVinculacion(this.cliente.cli_id, nuevo).subscribe({
      next: (resp) => {
        this.cargando = false;
        this.confirmando = false;
        this.vinculo = resp.data;
        if (nuevo) { this.toastr.success('Se generó un código nuevo. El anterior quedó anulado.'); }
      },
      error: (err) => {
        this.cargando = false;
        this.toastr.error(err.error?.message ?? 'No se pudo obtener el código de vinculación');
        this.cerrar();
      },
    });
  }

  generarNuevo(): void {
    this.cargar(true);
  }

  copiar(texto: string, etiqueta: string): void {
    navigator.clipboard.writeText(texto).then(
      () => this.toastr.success(`${etiqueta} copiado`),
      () => this.toastr.error('No se pudo copiar'),
    );
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}