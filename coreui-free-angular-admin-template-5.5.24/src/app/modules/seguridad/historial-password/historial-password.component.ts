import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { InputGroupComponent, InputGroupTextDirective } from '@coreui/angular';
import { AuditoriaService } from '../service/auditoria.service';
import { HistorialPasswordItem } from '../models/auditoria.model';
import { PaginadorComponent } from '../shared/paginador.component';

@Component({
  selector: 'app-historial-password',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, SpinnerModule, FormModule, PaginadorComponent,
    InputGroupComponent, InputGroupTextDirective],
  templateUrl: './historial-password.component.html',
})
export class HistorialPasswordComponent implements OnInit, OnDestroy {
  registros: HistorialPasswordItem[] = [];
  cargando = false;

  search = '';
  desde = '';
  hasta = '';

  total = 0;
  pagina = 1;
  readonly porPagina = 25;

  private busqueda$ = new Subject<void>();
  private busquedaSub?: Subscription;

  constructor(private service: AuditoriaService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();
    this.busquedaSub = this.busqueda$.pipe(debounceTime(400)).subscribe(() => this.cargar(1));
  }

  ngOnDestroy(): void {
    this.busquedaSub?.unsubscribe();
  }

  onSearchChange(): void {
    this.busqueda$.next();
  }

  cargar(pagina: number = 1): void {
    this.cargando = true;
    this.service.historialPassword({ search: this.search, desde: this.desde, hasta: this.hasta }, pagina).subscribe({
      next: (res) => {
        this.registros = res.data;
        this.total = res.total;
        this.pagina = pagina;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar el historial de contraseñas', 'Error');
        this.cargando = false;
      },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.desde = '';
    this.hasta = '';
    this.cargar(1);
  }
}
