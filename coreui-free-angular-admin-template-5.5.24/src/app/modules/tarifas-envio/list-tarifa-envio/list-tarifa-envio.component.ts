import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, SpinnerModule, TableModule } from '@coreui/angular';
import { TarifaEnvio } from '../models/tarifa-envio.model';
import { TarifaEnvioService } from '../service/tarifa-envio.service';
import { FormTarifaEnvioComponent } from '../form-tarifa-envio/form-tarifa-envio.component';
import { DeleteTarifaEnvioComponent } from '../delete-tarifa-envio/delete-tarifa-envio.component';

@Component({
  selector: 'app-list-tarifa-envio',
  standalone: true,
  imports: [
    CommonModule, CardModule, TableModule, ButtonModule, SpinnerModule,
    FormTarifaEnvioComponent, DeleteTarifaEnvioComponent,
  ],
  templateUrl: './list-tarifa-envio.component.html',
})
export class ListTarifaEnvioComponent implements OnInit {
  tarifas: TarifaEnvio[] = [];
  cargando = false;

  formModalVisible = false;
  deleteModalVisible = false;
  tarifaSeleccionada: TarifaEnvio | null = null;

  constructor(
    private service: TarifaEnvioService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.service.getAll().subscribe({
      next: (res) => {
        this.tarifas = res;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar las tarifas de envío', 'Error');
        this.cargando = false;
      },
    });
  }

  abrirNuevo(): void {
    this.tarifaSeleccionada = null;
    this.formModalVisible = true;
  }

  abrirEditar(t: TarifaEnvio): void {
    this.tarifaSeleccionada = { ...t };
    this.formModalVisible = true;
  }

  abrirEliminar(t: TarifaEnvio): void {
    this.tarifaSeleccionada = t;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.tarifaSeleccionada?.tev_id) return;

    this.service.delete(this.tarifaSeleccionada.tev_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Tarifa eliminada', 'Éxito');
        this.deleteModalVisible = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }
}