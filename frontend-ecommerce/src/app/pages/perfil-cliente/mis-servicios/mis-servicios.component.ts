import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { ServicioCliente } from '../../../core/models/servicio-tecnico.model';
import { ServicioTecnicoService } from '../../../core/services/servicio-tecnico.service';

/** C3 - Mis servicios (pestaña del perfil). */
@Component({
  selector: 'app-mis-servicios',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './mis-servicios.component.html',
  styleUrl: './mis-servicios.component.css',
})
export class MisServiciosComponent implements OnInit {
  servicios: ServicioCliente[] = [];
  cargando = true;
  error = false;

  constructor(private service: ServicioTecnicoService) {}

  ngOnInit(): void {
    this.service.listar().subscribe({
      next: (res) => { this.servicios = res.data; this.cargando = false; },
      error: () => { this.error = true; this.cargando = false; },
    });
  }

  claseEstado(s: ServicioCliente): string {
    if (s.cancelado) return 'ms-estado--cancelado';
    if (s.estado === 'LISTO' || s.estado === 'ENTREGADO') return 'ms-estado--ok';
    return 'ms-estado--proceso';
  }
}
