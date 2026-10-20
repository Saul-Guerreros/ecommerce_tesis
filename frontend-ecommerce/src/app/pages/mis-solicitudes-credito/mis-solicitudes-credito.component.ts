import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { SolicitudCreditoGenerada } from '../../core/models/credito.model';
import { CreditoService } from '../../core/services/credito.service';

/**
 * "Mis Solicitudes" del cliente: listado + detalle expandible inline.
 * Un detalle en ruta propia (/mis-solicitudes-credito/:id) habría sido
 * otra opción, pero con una sola solicitud a la vez visible por fila y
 * sin necesidad de compartir el link del detalle, expandir en el mismo
 * listado evita una pantalla extra y un segundo viaje de router.
 */
@Component({
  selector: 'app-mis-solicitudes-credito',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, RouterModule],
  templateUrl: './mis-solicitudes-credito.component.html',
  styleUrl: './mis-solicitudes-credito.component.css',
})
export class MisSolicitudesCreditoComponent implements OnInit {
  solicitudes: SolicitudCreditoGenerada[] = [];
  cargando = true;

  /** scr_id actualmente expandida, o null si ninguna. */
  expandidaId: number | null = null;
  detalle: SolicitudCreditoGenerada | null = null;
  cargandoDetalle = false;

  constructor(
    private creditoService: CreditoService,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.creditoService.listar().subscribe({
      next: (resp) => {
        this.solicitudes = resp.data;
        this.cargando = false;
      },
      error: () => {
        this.cargando = false;
        this.toastr.error('No pudimos cargar tus solicitudes.', 'Error');
      },
    });
  }

  toggleDetalle(solicitud: SolicitudCreditoGenerada): void {
    if (this.expandidaId === solicitud.scr_id) {
      this.expandidaId = null;
      this.detalle = null;
      return;
    }

    this.expandidaId = solicitud.scr_id;
    this.detalle = null;
    this.cargandoDetalle = true;

    this.creditoService.detalle(solicitud.scr_id).subscribe({
      next: (resp) => {
        this.detalle = resp.data;
        this.cargandoDetalle = false;
      },
      error: () => {
        this.cargandoDetalle = false;
        this.toastr.error('No pudimos cargar el detalle de la solicitud.', 'Error');
      },
    });
  }

  /** Clase de color del badge, según el código de estado del backend. */
  claseEstado(codigo: string): string {
    const clases: Record<string, string> = {
      PENDIENTE: 'uk-label',
      EN_REVISION: 'uk-label uk-label-warning',
      APROBADA: 'uk-label uk-label-success',
      RECHAZADA: 'uk-label uk-label-danger',
      ANULADA: 'uk-label',
    };
    return clases[codigo] ?? 'uk-label';
  }
}