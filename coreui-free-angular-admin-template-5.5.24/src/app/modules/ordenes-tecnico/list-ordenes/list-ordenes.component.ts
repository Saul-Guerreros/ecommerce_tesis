import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { OrdenTarjeta } from '../models/orden-tecnico.model';
import { OrdenesTecnicoService } from '../service/ordenes-tecnico.service';

interface Columna {
  titulo: string;
  estados: string[];
  color: string;
}

/** T1 - Tablero (kanban) de órdenes del técnico, agrupado por estado. */
@Component({
  selector: 'app-list-ordenes',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, CardModule, ButtonModule, BadgeModule, FormModule, SpinnerModule],
  templateUrl: './list-ordenes.component.html',
  styleUrl: './list-ordenes.component.scss',
})
export class ListOrdenesComponent implements OnInit, OnDestroy {
  readonly columnas: Columna[] = [
    { titulo: 'Recibidas', estados: ['RECIBIDA'], color: 'warning' },
    { titulo: 'En diagnóstico', estados: ['EN_DIAGNOSTICO'], color: 'info' },
    { titulo: 'Esperando al cliente', estados: ['PRESUPUESTADA'], color: 'secondary' },
    { titulo: 'Para reparar', estados: ['APROBADA', 'EN_REPARACION'], color: 'primary' },
    // Garantía de compra: el taller gestiona el reclamo con la empresa responsable.
    { titulo: 'Garantía con la empresa', estados: ['GESTION_GARANTIA', 'EN_EMPRESA'], color: 'dark' },
    { titulo: 'Listas', estados: ['LISTA'], color: 'success' },
  ];

  ordenes: OrdenTarjeta[] = [];
  esAdmin = false;
  tecnicos: { usu_id: number; nombre: string }[] = [];
  cargando = false;

  buscar = '';
  filtroTecnico = '';
  private searchSubject = new Subject<void>();
  private searchSub?: Subscription;

  constructor(private service: OrdenesTecnicoService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();
    this.searchSub = this.searchSubject.pipe(debounceTime(400)).subscribe(() => this.cargar());
  }

  ngOnDestroy(): void {
    this.searchSub?.unsubscribe();
  }

  onSearchChange(): void {
    this.searchSubject.next();
  }

  cargar(): void {
    this.cargando = true;
    this.service.tablero({ buscar: this.buscar, tecnico: this.filtroTecnico }).subscribe({
      next: (res) => {
        this.ordenes = res.data;
        this.esAdmin = res.meta.es_admin;
        this.tecnicos = res.meta.tecnicos;
        this.cargando = false;
      },
      error: () => { this.toastr.error('No se pudieron cargar las órdenes', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void {
    this.buscar = '';
    this.filtroTecnico = '';
    this.cargar();
  }

  de(col: Columna): OrdenTarjeta[] {
    return this.ordenes.filter((o) => col.estados.includes(o.estado));
  }
}
