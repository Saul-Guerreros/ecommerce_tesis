import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Empresa } from '../models/empresa.model';
import { EmpresasService } from '../service/empresas.service';
import { FormEmpresaComponent } from '../form-empresa/form-empresa.component';
import { DeleteEmpresaComponent } from '../delete-empresa/delete-empresa.component';

@Component({
  selector: 'app-list-empresa',
  standalone: true,
  imports: [
    CommonModule,
    CardModule,
    TableModule,
    ButtonModule,
    BadgeModule,
    SpinnerModule,
    FormEmpresaComponent,
    DeleteEmpresaComponent,
  ],
  templateUrl: './list-empresa.component.html',
})
export class ListEmpresaComponent implements OnInit {
  empresas: Empresa[] = [];
  cargando = false;

  formModalVisible = false;
  deleteModalVisible = false;
  empresaSeleccionada: Empresa | null = null;

  constructor(
    private empresasService: EmpresasService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.empresasService.getAll().subscribe({
      next: (res) => {
        this.empresas = res;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar las empresas', 'Error');
        this.cargando = false;
      },
    });
  }

  abrirNuevo(): void {
    this.empresaSeleccionada = null;   // modo alta
    this.formModalVisible = true;
  }

  abrirEditar(empresa: Empresa): void {
    this.empresaSeleccionada = { ...empresa };  // copia: no se muta la fila de la tabla
    this.formModalVisible = true;
  }

  abrirEliminar(empresa: Empresa): void {
    this.empresaSeleccionada = empresa;
    this.deleteModalVisible = true;
  }

  onGuardado(): void {
    this.cargar();
  }

  confirmarEliminar(): void {
    if (!this.empresaSeleccionada?.emp_id) return;

    this.empresasService.delete(this.empresaSeleccionada.emp_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Empresa eliminada', 'Éxito');
        this.deleteModalVisible = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar la empresa', 'Error');
        this.deleteModalVisible = false;
      },
    });
  }

  cambiarEstado(empresa: Empresa): void {
    this.empresasService.toggleEstado(empresa.emp_id!).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito');
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error');
      },
    });
  }
}
