import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, FormModule, GridModule, SpinnerModule, TableModule } from '@coreui/angular';
import { ClienteService } from '../service/cliente.service';
import { Cliente } from '../models/cliente.model';
import { ClienteFormComponent } from '../cliente-form/cliente-form.component';
import { ClienteDeleteComponent } from '../cliente-delete/cliente-delete.component';
import { ContactoPrincipalPipe } from '../pipes/contacto-principal.pipe';

@Component({
  selector: 'app-cliente-list',
  standalone: true,
  imports: [
    CommonModule, FormsModule,
    CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule, GridModule,
    ClienteFormComponent, ClienteDeleteComponent, ContactoPrincipalPipe,
  ],
  templateUrl: './cliente-list.component.html',
})
export class ClienteListComponent implements OnInit {

  clientes: Cliente[] = [];
  search = '';
  estado = '';
  total = 0;
  currentPage = 1;
  perPage = 25;

  isLoading$: Observable<boolean>;

  showForm = false;
  showDelete = false;
  clienteSel: Cliente | null = null;

  constructor(
    public clienteService: ClienteService,
    private toastr: ToastrService,
  ) {
    this.isLoading$ = this.clienteService.isLoading$;
  }

  ngOnInit(): void {
    this.listar();
  }

  listar(page: number = 1): void {
    this.clienteService.list(page, this.search, this.estado).subscribe({
      next: (resp) => {
        this.clientes = resp.data;
        this.total = resp.total;
        this.currentPage = page;
      },
      error: () => this.toastr.error('No se pudo cargar el listado de clientes'),
    });
  }

  buscar(): void {
    this.listar(1);
  }

  limpiarFiltros(): void {
    this.search = '';
    this.estado = '';
    this.listar(1);
  }

  get totalPaginas(): number {
    return Math.ceil(this.total / this.perPage);
  }

  nuevo(): void {
    this.clienteSel = null;
    this.showForm = true;
  }

  editar(cliente: Cliente): void {
    this.clienteSel = cliente;
    this.showForm = true;
  }

  confirmarEliminar(cliente: Cliente): void {
    this.clienteSel = cliente;
    this.showDelete = true;
  }

  onGuardado(ev: { data: Cliente; isEdit: boolean }): void {
    if (ev.isEdit) {
      const i = this.clientes.findIndex(c => c.cli_id === ev.data.cli_id);
      if (i !== -1) { this.clientes[i] = ev.data; }
    } else {
      this.clientes.unshift(ev.data);
      this.total++;
    }
    this.showForm = false;
  }

  onEliminado(id: number): void {
    const i = this.clientes.findIndex(c => c.cli_id === id);
    if (i !== -1) {
      this.clientes.splice(i, 1);
      this.total--;
    }
    this.showDelete = false;
  }

  cambiarEstado(cliente: Cliente): void {
    this.clienteService.cambiarEstado(cliente.cli_id).subscribe({
      next: (resp) => {
        cliente.cli_estado = resp.data.cli_estado;
        this.toastr.success(resp.message);
      },
      error: (err) => this.toastr.error(
        err.error?.message ?? 'Ocurrió un error inesperado'
      ),
    });
  }
}