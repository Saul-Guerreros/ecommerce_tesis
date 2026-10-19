import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { PresupuestoFila } from '../models/presupuesto-validacion.model';
import { PresupuestosValidacionService } from '../service/presupuestos-validacion.service';
import { RevisarPresupuestoComponent } from '../revisar-presupuesto/revisar-presupuesto.component';

@Component({
  selector: 'app-list-presupuesto',
  standalone: true,
  imports: [CommonModule, FormsModule, BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule, RevisarPresupuestoComponent],
  templateUrl: './list-presupuesto.component.html',
})
export class ListPresupuestoComponent implements OnInit, OnDestroy {
  readonly estados = [
    { codigo: 'EN_REVISION', descri: 'Pendientes de validación' },
    { codigo: 'BORRADOR', descri: 'Borradores' },
    { codigo: 'ENVIADO', descri: 'Enviados (esperando al cliente)' },
    { codigo: 'APROBADO', descri: 'Aprobados' },
    { codigo: 'RECHAZADO', descri: 'Rechazados' },
    { codigo: 'VENCIDO', descri: 'Vencidos' },
    { codigo: '', descri: 'Todos' },
  ];

  presupuestos: PresupuestoFila[] = [];
  cargando = false;
  filtroEstado = 'EN_REVISION';
  buscar = '';
  private searchSubject = new Subject<void>();
  private searchSub?: Subscription;

  seleccionadoId: number | null = null;
  modalVisible = false;

  constructor(private service: PresupuestosValidacionService, private toastr: ToastrService) {}

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
    this.service.getAll({ estado: this.filtroEstado, buscar: this.buscar }).subscribe({
      next: (res) => { this.presupuestos = res.data; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar los presupuestos', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void {
    this.filtroEstado = 'EN_REVISION';
    this.buscar = '';
    this.cargar();
  }

  revisar(p: PresupuestoFila): void {
    this.seleccionadoId = p.pse_id;
    this.modalVisible = true;
  }

  color(codigo: string): string {
    switch (codigo) {
      case 'EN_REVISION': return 'warning';
      case 'ENVIADO': return 'info';
      case 'APROBADO': return 'success';
      case 'RECHAZADO': case 'VENCIDO': return 'danger';
      default: return 'secondary';
    }
  }
}
