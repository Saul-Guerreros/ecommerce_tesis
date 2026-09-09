import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { AjusteInventario, DepositoOption } from '../models/ajuste-inventario.model';
import { AjustesInventarioService } from '../service/ajustes-inventario.service';
import { FormAjusteComponent } from '../form-ajuste/form-ajuste.component';

@Component({
  selector: 'app-list-ajuste',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule, ModalModule,
    FormAjusteComponent,
  ],
  templateUrl: './list-ajuste.component.html',
})
export class ListAjusteComponent implements OnInit {
  ajustes: AjusteInventario[] = [];
  depositos: DepositoOption[] = [];
  cargando = false;

  search = '';
  filtroDeposito: number | null = null;
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  soloLectura = false;
  ajusteSeleccionado: AjusteInventario | null = null;

  // Modal de confirmacion generico, reutilizado para Confirmar / Anular / Eliminar
  confirmModalVisible = false;
  ajusteAAccionar: AjusteInventario | null = null;
  accionPendiente: 'confirmar' | 'anular' | 'eliminar' | null = null;

  constructor(private service: AjustesInventarioService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();

    this.service.getConfig().subscribe({
      next: (res) => (this.depositos = res.depositos),
      error: () => {},
    });

    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;
    this.service
      .getAll({ search: this.search, deposito: this.filtroDeposito, estado: this.filtroEstado })
      .subscribe({
        next: (res) => { this.ajustes = res; this.cargando = false; },
        error: () => { this.toastr.error('No se pudieron cargar los ajustes', 'Error'); this.cargando = false; },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroDeposito = null;
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.ajusteSeleccionado = null;
    this.soloLectura = false;
    this.formModalVisible = true;
  }

  abrirVer(a: AjusteInventario): void {
    this.cargando = true;
    this.service.getById(a.aji_id!).subscribe({
      next: (completo) => {
        this.ajusteSeleccionado = completo;
        this.soloLectura = true;
        this.formModalVisible = true;
        this.cargando = false;
      },
      error: () => { this.toastr.error('No se pudo cargar el detalle', 'Error'); this.cargando = false; },
    });
  }

  // ==================== Acciones directas desde la tabla ====================

  pedirConfirmar(a: AjusteInventario): void {
    this.ajusteAAccionar = a;
    this.accionPendiente = 'confirmar';
    this.confirmModalVisible = true;
  }

  pedirAnular(a: AjusteInventario): void {
    this.ajusteAAccionar = a;
    this.accionPendiente = 'anular';
    this.confirmModalVisible = true;
  }

  pedirEliminar(a: AjusteInventario): void {
    this.ajusteAAccionar = a;
    this.accionPendiente = 'eliminar';
    this.confirmModalVisible = true;
  }

  cancelarAccion(): void {
    this.confirmModalVisible = false;
    this.ajusteAAccionar = null;
    this.accionPendiente = null;
  }

  ejecutarAccion(): void {
    if (!this.ajusteAAccionar?.aji_id || !this.accionPendiente) return;

    const id = this.ajusteAAccionar.aji_id;
    const peticion = this.accionPendiente === 'confirmar' ? this.service.confirmar(id)
                    : this.accionPendiente === 'anular'    ? this.service.anular(id)
                    : this.service.eliminarBorrador(id);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Operación realizada', 'Éxito');
        this.confirmModalVisible = false;
        this.formModalVisible = false; // por si la accion vino del footer del modal Ver
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo completar la operación', 'Error');
        this.confirmModalVisible = false;
      },
    });
  }

  get tituloAccion(): string {
    switch (this.accionPendiente) {
      case 'confirmar': return 'Confirmar ajuste';
      case 'anular':     return 'Anular ajuste';
      case 'eliminar':   return 'Eliminar borrador';
      default:           return '';
    }
  }

  get mensajeAccion(): string {
    switch (this.accionPendiente) {
      case 'confirmar':
        return `¿Confirmar el ajuste N° ${this.ajusteAAccionar?.aji_id}? Esto impactará el stock y el costo promedio ponderado de los productos involucrados.`;
      case 'anular':
        return `¿Anular el ajuste N° ${this.ajusteAAccionar?.aji_id}? El stock revertirá al estado anterior.`;
      case 'eliminar':
        return `¿Eliminar el borrador N° ${this.ajusteAAccionar?.aji_id}? No afectó el stock, se puede eliminar sin riesgo.`;
      default:
        return '';
    }
  }
}
