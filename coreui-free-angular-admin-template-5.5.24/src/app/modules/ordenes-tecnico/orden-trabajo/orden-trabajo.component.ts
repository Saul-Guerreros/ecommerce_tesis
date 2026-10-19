import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { forkJoin } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, SpinnerModule } from '@coreui/angular';
import { OrdenConfig, OrdenTrabajo } from '../models/orden-tecnico.model';
import { OrdenesTecnicoService } from '../service/ordenes-tecnico.service';
import { FormDiagnosticoComponent } from '../form-diagnostico/form-diagnostico.component';
import { CerrarNoReparableComponent } from '../cerrar-no-reparable/cerrar-no-reparable.component';
import { FormPresupuestoComponent } from '../form-presupuesto/form-presupuesto.component';
import { EjecucionOrdenComponent } from '../ejecucion-orden/ejecucion-orden.component';

/**
 * Página de la orden (contenedor): datos + T2 diagnóstico + T3 presupuesto
 * + T4 ejecución y cierre (con presupuesto complementario).
 */
@Component({
  selector: 'app-orden-trabajo',
  standalone: true,
  imports: [CommonModule, RouterModule, BadgeModule, ButtonModule, CardModule, SpinnerModule,
    FormDiagnosticoComponent, CerrarNoReparableComponent, FormPresupuestoComponent, EjecucionOrdenComponent],
  templateUrl: './orden-trabajo.component.html',
})
export class OrdenTrabajoComponent implements OnInit {
  orden: OrdenTrabajo | null = null;
  config: OrdenConfig | null = null;
  cargando = true;
  iniciando = false;

  clave: string | null = null;
  cargandoClave = false;
  modalNoReparable = false;

  private sseId = 0;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private service: OrdenesTecnicoService,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.sseId = Number(this.route.snapshot.paramMap.get('id'));
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    forkJoin({ orden: this.service.show(this.sseId), config: this.service.config(this.sseId) }).subscribe({
      next: ({ orden, config }) => {
        this.orden = orden.data;
        this.config = config;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo abrir la orden', 'Error');
        this.router.navigate(['/servicios/mis-ordenes']);
      },
    });
  }

  fotosDe(tipo: string) {
    return this.orden?.adjuntos.filter((a) => a.sad_tipo === tipo) ?? [];
  }

  iniciar(): void {
    this.iniciando = true;
    this.service.iniciar(this.sseId).subscribe({
      next: (res) => { this.toastr.success(res.message, 'Éxito'); this.orden = res.data; this.iniciando = false; },
      error: (err) => {
        this.iniciando = false;
        const msg = err?.error?.errors ? Object.values(err.error.errors as Record<string, string[]>)[0]?.[0] : null;
        this.toastr.error(msg ?? err?.error?.message ?? 'No se pudo iniciar la orden', 'Error');
      },
    });
  }

  /** La consulta queda registrada en el historial (auditoría). */
  verClave(): void {
    if (this.clave !== null) { this.clave = null; return; }
    this.cargandoClave = true;
    this.service.verClave(this.sseId).subscribe({
      next: (res) => { this.clave = res.data.clave ?? '(sin clave)'; this.cargandoClave = false; },
      error: () => { this.cargandoClave = false; this.toastr.error('No se pudo obtener la clave', 'Error'); },
    });
  }

  onActualizada(orden: OrdenTrabajo): void {
    this.orden = orden;
  }

  procesando = false;

  /** Acciones simples de un clic (iniciar/reanudar reparación, abrir/descartar complementario). */
  ejecutar(accion: 'reparar' | 'complementario' | 'descartar'): void {
    const peticion = {
      reparar: () => this.service.iniciarReparacion(this.sseId),
      complementario: () => this.service.abrirComplementario(this.sseId),
      descartar: () => this.service.descartarComplementario(this.sseId),
    }[accion]();

    this.procesando = true;
    peticion.subscribe({
      next: (res) => { this.procesando = false; this.orden = res.data; this.toastr.success(res.message, 'Éxito'); },
      error: (err) => {
        this.procesando = false;
        const msg = err?.error?.errors ? Object.values(err.error.errors as Record<string, string[]>)[0]?.[0] : null;
        this.toastr.error(msg ?? err?.error?.message ?? 'No se pudo completar la acción', 'Error');
      },
    });
  }

  get complementarioPendiente(): boolean {
    const p = this.orden?.presupuesto;
    return !!p && p.pse_versio > 1 && ['EN_REVISION', 'ENVIADO'].includes(p.estado.codigo)
      && this.orden?.estado.codigo !== 'LISTA';
  }

  colorPresupuesto(codigo: string): string {
    switch (codigo) {
      case 'APROBADO': return 'success';
      case 'RECHAZADO': case 'VENCIDO': return 'danger';
      case 'ENVIADO': return 'info';
      case 'EN_REVISION': return 'warning';
      default: return 'secondary';
    }
  }
}
