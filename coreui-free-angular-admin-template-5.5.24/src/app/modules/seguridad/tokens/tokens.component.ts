import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { AuditoriaService } from '../service/auditoria.service';
import { EstadoToken, TokenItem } from '../models/auditoria.model';
import { PaginadorComponent } from '../shared/paginador.component';

@Component({
  selector: 'app-tokens',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    PaginadorComponent,
  ],
  templateUrl: './tokens.component.html',
})
export class TokensComponent implements OnInit, OnDestroy {
  registros: TokenItem[] = [];
  cargando = false;

  search = '';
  tipo = '';
  estado = '';

  total = 0;
  vigentes = 0;
  pagina = 1;
  readonly porPagina = 25;

  readonly colorEstado: Record<EstadoToken, string> = {
    VIGENTE: 'success',
    USADO: 'info',
    EXPIRADO: 'secondary',
  };

  readonly etiquetaEstado: Record<EstadoToken, string> = {
    VIGENTE: 'Vigente',
    USADO: 'Usado',
    EXPIRADO: 'Expirado',
  };

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
    this.service.tokens({ search: this.search, tipo: this.tipo, estado: this.estado }, pagina).subscribe({
      next: (res) => {
        this.registros = res.data;
        this.total = res.total;
        this.vigentes = res.resumen?.vigentes ?? 0;
        this.pagina = pagina;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar los tokens de verificación', 'Error');
        this.cargando = false;
      },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.tipo = '';
    this.estado = '';
    this.cargar(1);
  }
}
