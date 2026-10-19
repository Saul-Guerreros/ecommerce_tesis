import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { SolicitudServicioDetalle } from '../models/solicitud-servicio.model';
import { SolicitudesServicioService } from '../service/solicitudes-servicio.service';
import { PanelServiciosService } from '../../panel-servicios/service/panel-servicios.service';

/** Ficha de solo lectura: datos, fotos, recepción e historial (trazabilidad). */
@Component({
  selector: 'app-detalle-solicitud',
  standalone: true,
  imports: [CommonModule, BadgeModule, ButtonModule, ModalModule, SpinnerModule, TableModule],
  templateUrl: './detalle-solicitud.component.html',
})
export class DetalleSolicitudComponent implements OnChanges {
  @Input() visible = false;
  @Input() solicitudId: number | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();

  detalle: SolicitudServicioDetalle | null = null;
  cargando = false;

  constructor(
    private service: SolicitudesServicioService,
    private panel: PanelServiciosService,
    private toastr: ToastrService,
  ) {}

  /** A2: constancia de recepción en PDF (respaldo de la tenencia del equipo). */
  imprimirRecepcion(): void {
    if (!this.detalle) return;
    const numero = this.detalle.sse_numero;
    this.panel.constancia(this.detalle.sse_id, 'recepcion').subscribe({
      next: (pdf) => this.panel.abrirPdf(pdf, `constancia-recepcion-${numero}.pdf`),
      error: () => this.toastr.error('No se pudo generar la constancia', 'Error'),
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.solicitudId) {
      this.detalle = null;
      this.cargando = true;
      this.service.show(this.solicitudId).subscribe({
        next: (res) => { this.detalle = res.data; this.cargando = false; },
        error: () => { this.cargando = false; this.toastr.error('No se pudo cargar la solicitud', 'Error'); },
      });
    }
  }

  fotosDe(tipo: string) {
    return this.detalle?.adjuntos.filter((a) => a.sad_tipo === tipo) ?? [];
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
