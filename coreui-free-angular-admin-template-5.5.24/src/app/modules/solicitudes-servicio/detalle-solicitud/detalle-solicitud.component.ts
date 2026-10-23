import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { SolicitudServicioDetalle } from '../models/solicitud-servicio.model';
import { SolicitudesServicioService } from '../service/solicitudes-servicio.service';
import { PanelServiciosService } from '../../panel-servicios/service/panel-servicios.service';
import { RegistrarRespuestaComponent } from '../../panel-servicios/registrar-respuesta/registrar-respuesta.component';
import { RegistrarContactoComponent } from '../../panel-servicios/registrar-contacto/registrar-contacto.component';
import { MedioContacto } from '../../panel-servicios/models/panel-servicio.model';
import { PagosSolicitudComponent } from '../../cobros-servicio/pagos-solicitud/pagos-solicitud.component';

/** Ficha de la solicitud: datos, fotos, recepción, pagos e historial (trazabilidad). */
@Component({
  selector: 'app-detalle-solicitud',
  standalone: true,
  imports: [
    CommonModule, BadgeModule, ButtonModule, ModalModule, SpinnerModule, TableModule,
    RegistrarRespuestaComponent, RegistrarContactoComponent, PagosSolicitudComponent,
  ],
  templateUrl: './detalle-solicitud.component.html',
})
export class DetalleSolicitudComponent implements OnChanges {
  @Input() visible = false;
  @Input() solicitudId: number | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  /** Se registró una respuesta o un contacto: la lista que abrió el detalle debe refrescarse. */
  @Output() actualizado = new EventEmitter<void>();

  detalle: SolicitudServicioDetalle | null = null;
  cargando = false;

  // Paso 2: aprobación asistida y contacto
  respuestaVisible = false;
  contactoVisible = false;
  medioContacto: MedioContacto | null = null;

  constructor(
    private service: SolicitudesServicioService,
    private panel: PanelServiciosService,
    private toastr: ToastrService,
    private router: Router,
  ) {}

  /** Paso 3: abre el wizard de mostrador con el cliente y el equipo de esta solicitud bloqueados. */
  registrarReclamo(): void {
    if (!this.detalle) return;
    const numero = this.detalle.sse_numero;
    this.cerrar();
    this.router.navigate(['/servicios/solicitudes/mostrador'], { queryParams: { reclamo: numero } });
  }

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
      this.recargar();
    }
  }

  recargar(): void {
    if (!this.solicitudId) return;
    this.cargando = true;
    this.service.show(this.solicitudId).subscribe({
      next: (res) => { this.detalle = res.data; this.cargando = false; },
      error: () => { this.cargando = false; this.toastr.error('No se pudo cargar la solicitud', 'Error'); },
    });
  }

  imprimirPresupuesto(): void {
    if (!this.detalle) return;
    const numero = this.detalle.presupuesto?.numero ?? this.detalle.sse_numero;
    this.panel.pdfPresupuesto(this.detalle.sse_id).subscribe({
      next: (pdf) => this.panel.abrirPdf(pdf, `presupuesto-${numero}.pdf`),
      error: () => this.toastr.error('No se pudo generar el presupuesto', 'Error'),
    });
  }

  onGuardado(): void {
    this.recargar();
    this.actualizado.emit();
  }

  abrirContacto(medio: MedioContacto | null = null): void {
    this.medioContacto = medio;
    this.contactoVisible = true;
  }

  /** D.13-b: abre WhatsApp y pide confirmar el contacto (solo si hay un presupuesto esperando). */
  avisarWhatsapp(): void {
    const url = this.detalle?.cliente.whatsapp_url;
    if (!url) return;
    window.open(url, '_blank', 'noopener');
    if (this.detalle?.presupuesto?.codigo === 'ENVIADO') this.abrirContacto('WHATSAPP');
  }

  fotosDe(tipo: string) {
    return this.detalle?.adjuntos.filter((a) => a.sad_tipo === tipo) ?? [];
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
