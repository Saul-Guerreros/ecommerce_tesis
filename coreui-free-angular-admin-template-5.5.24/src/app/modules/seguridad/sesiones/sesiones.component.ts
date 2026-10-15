import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { AuditoriaService } from '../service/auditoria.service';
import { EstadoSesion, SesionItem } from '../models/auditoria.model';
import { PaginadorComponent } from '../shared/paginador.component';
import { DispositivoPipe } from '../shared/dispositivo.pipe';

@Component({
  selector: 'app-sesiones',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    PaginadorComponent, DispositivoPipe,
  ],
  templateUrl: './sesiones.component.html',
})
export class SesionesComponent implements OnInit, OnDestroy {
  registros: SesionItem[] = [];
  cargando = false;

  search = '';
  estado = 'activas';

  total = 0;
  activasAhora = 0;
  pagina = 1;
  readonly porPagina = 25;

  readonly colorEstado: Record<EstadoSesion, string> = {
    ACTIVA: 'success',
    EXPIRADA: 'secondary',
    REVOCADA: 'danger',
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
    this.service.sesiones({ search: this.search, estado: this.estado }, pagina).subscribe({
      next: (res) => {
        this.registros = res.data;
        this.total = res.total;
        this.activasAhora = res.resumen?.activas_ahora ?? 0;
        this.pagina = pagina;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar las sesiones', 'Error');
        this.cargando = false;
      },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.estado = 'activas';
    this.cargar(1);
  }
}
