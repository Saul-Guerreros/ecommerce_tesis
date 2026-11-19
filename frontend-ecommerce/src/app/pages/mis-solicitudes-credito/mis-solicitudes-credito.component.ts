import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { SolicitudCreditoGenerada, claseEstadoSolicitud } from '../../core/models/credito.model';
import { CreditoService } from '../../core/services/credito.service';

/**
 * "Mis Solicitudes de Crédito" (D.1): listado con N.º, fecha, total y
 * estado. Al hacer clic sobre una fila se entra al detalle y la aceptación
 * de condiciones (D.2), que vive en su propia ruta:
 * /mis-solicitudes-credito/:id.
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

  constructor(
    private creditoService: CreditoService,
    private toastr: ToastrService,
    private router: Router,
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

    // Re-sincroniza el número rojo del menú con lo que muestra la lista.
    this.creditoService.cargarPendientes();
  }

  verDetalle(solicitud: SolicitudCreditoGenerada): void {
    this.router.navigate(['/mis-solicitudes-credito', solicitud.scr_id]);
  }

  claseEstado(codigo: string): string {
    return claseEstadoSolicitud(codigo);
  }

  trackById(_: number, solicitud: SolicitudCreditoGenerada): number {
    return solicitud.scr_id;
  }
}