import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, Subscription, debounceTime } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { InputGroupComponent, InputGroupTextDirective } from '@coreui/angular';
import { AuditoriaService } from '../service/auditoria.service';
import { IntentoSesionItem } from '../models/auditoria.model';
import { PaginadorComponent } from '../shared/paginador.component';
import { DispositivoPipe } from '../shared/dispositivo.pipe';

@Component({
  selector: 'app-intentos-sesion',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    PaginadorComponent, DispositivoPipe, InputGroupComponent, InputGroupTextDirective,
  ],
  templateUrl: './intentos-sesion.component.html',
})
export class IntentosSesionComponent implements OnInit, OnDestroy {
  registros: IntentoSesionItem[] = [];
  cargando = false;

  search = '';
  resultado = '';
  desde = '';
  hasta = '';

  total = 0;
  fallidos = 0;
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
    this.service.intentosSesion(
      { search: this.search, resultado: this.resultado, desde: this.desde, hasta: this.hasta }, pagina,
    ).subscribe({
      next: (res) => {
        this.registros = res.data;
        this.total = res.total;
        this.fallidos = res.resumen?.fallidos ?? 0;
        this.pagina = pagina;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar los intentos de sesión', 'Error');
        this.cargando = false;
      },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.resultado = '';
    this.desde = '';
    this.hasta = '';
    this.cargar(1);
  }
}
