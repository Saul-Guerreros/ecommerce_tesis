import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { FuncionarioService } from '../service/funcionario.service';
import { Cargo, Funcionario } from '../models/funcionario.model';
import { FuncionarioFormComponent } from '../funcionario-form/funcionario-form.component';
import { FuncionarioDeleteComponent } from '../funcionario-delete/funcionario-delete.component';
import { ContactoPrincipalPipe } from '../../clientes/pipes/contacto-principal.pipe';

@Component({
  selector: 'app-funcionario-list',
  standalone: true,
  imports: [
    CommonModule, FormsModule,
    CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FuncionarioFormComponent, FuncionarioDeleteComponent, ContactoPrincipalPipe,
  ],
  templateUrl: './funcionario-list.component.html',
})
export class FuncionarioListComponent implements OnInit, OnDestroy {

  funcionarios: Funcionario[] = [];
  cargos: Cargo[] = [];
  cargando = false;

  search = '';
  filtroEstado = '';
  filtroCargo = '';

  total = 0;
  currentPage = 1;
  perPage = 25;

  showForm = false;
  showDelete = false;
  funcionarioSel: Funcionario | null = null;

  private searchSubject = new Subject<string>();
  private searchSub?: Subscription;

  constructor(
    private service: FuncionarioService,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.cargarCargos();
    this.listar();
    this.searchSub = this.searchSubject
      .pipe(debounceTime(400), distinctUntilChanged())
      .subscribe(() => this.listar(1));
  }

  ngOnDestroy(): void {
    this.searchSub?.unsubscribe();
  }

  private cargarCargos(): void {
    this.service.config().subscribe({
      next: (c) => (this.cargos = c.cargos),
      error: () => this.toastr.error('No se pudieron cargar los cargos para el filtro'),
    });
  }

  listar(page: number = 1): void {
    this.cargando = true;
    this.service.list(page, this.search, this.filtroEstado, this.filtroCargo).subscribe({
      next: (resp) => {
        this.funcionarios = resp.data;
        this.total = resp.total;
        this.currentPage = page;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar el listado de funcionarios', 'Error');
        this.cargando = false;
      },
    });
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.filtroCargo = '';
    this.listar(1);
  }

  get totalPaginas(): number {
    return Math.ceil(this.total / this.perPage);
  }

  nuevo(): void {
    this.funcionarioSel = null;
    this.showForm = true;
  }

  editar(f: Funcionario): void {
    this.funcionarioSel = f;
    this.showForm = true;
  }

  confirmarEliminar(f: Funcionario): void {
    this.funcionarioSel = f;
    this.showDelete = true;
  }

  onGuardado(ev: { data: Funcionario; isEdit: boolean }): void {
    this.showForm = false;
    this.listar(ev.isEdit ? this.currentPage : 1);
  }

  onEliminado(): void {
    this.showDelete = false;
    const pagina = this.funcionarios.length === 1 && this.currentPage > 1
      ? this.currentPage - 1
      : this.currentPage;
    this.listar(pagina);
  }

  cambiarEstado(f: Funcionario): void {
    this.service.cambiarEstado(f.fun_id).subscribe({
      next: (resp) => {
        f.fun_estado = resp.data.fun_estado;
        this.toastr.success(resp.message, 'Éxito');
      },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}
