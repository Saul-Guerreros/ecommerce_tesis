import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  AlertModule, BadgeModule, ButtonModule, CardModule, FormModule,
  NavModule, SpinnerModule, TableModule,
} from '@coreui/angular';

import { MotivoRechazoOption } from '../models/solicitud-credito.model';
import {
  CuotaCronograma, DecisionPayload, DetalleSolicitudLinea, SolicitudCreditoDetalle,
} from '../models/solicitud-credito-detalle.model';
import { SolicitudesCreditoService } from '../service/solicitudes-credito.service';
import { CambiarPlanComponent } from '../cambiar-plan/cambiar-plan.component';

type Pestana = 'resumen' | 'solicitante' | 'laboral' | 'referencias' | 'compra' | 'evaluacion';

/**
 * Pantalla de gestión de una solicitud.
 *
 * Todas las acciones devuelven la solicitud completa, así que el estado
 * se refresca con el 'data' de la respuesta en vez de volver a pedir el
 * detalle: una petición menos y sin ventana donde la pantalla muestre
 * datos viejos.
 *
 * La habilitación de los botones viene de 'acciones', calculada por el
 * backend con la misma regla que aplica el Service. Decidirla acá
 * separaría las dos reglas y la pantalla terminaría ofreciendo acciones
 * que el servidor rechaza.
 */
@Component({
  selector: 'app-detalle-solicitud-credito',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule, AlertModule, NavModule,
    CambiarPlanComponent,
  ],
  templateUrl: './detalle-solicitud-credito.component.html',
})
export class DetalleSolicitudCreditoComponent implements OnInit {
  solicitud: SolicitudCreditoDetalle | null = null;
  cargando = false;
  procesando = false;

  pestana: Pestana = 'resumen';

  motivos: MotivoRechazoOption[] = [];
  vigenciaDefault = 7;

  // ---- Formulario de la pestaña Evaluación ----
  observaciones = '';
  decision: 'APROBAR' | 'RECHAZAR' | null = null;
  vigenciaDias: number | null = null;
  motivo = '';
  detalleRechazo = '';

  // ---- Pestaña Compra y plan ----
  fechaPrimeraCuota = '';
  modalPlanVisible = false;
  lineaEnEdicion: DetalleSolicitudLinea | null = null;

  constructor(
    private service: SolicitudesCreditoService,
    private toastr: ToastrService,
    private router: Router,
    private ruta: ActivatedRoute,
  ) {}

  ngOnInit(): void {
    const id = Number(this.ruta.snapshot.paramMap.get('id'));

    if (!id) {
      this.router.navigate(['/solicitudes-credito']);
      return;
    }

    this.service.config().subscribe({
      next: (res) => {
        this.motivos = res.motivos_rechazo;
        this.vigenciaDefault = res.vigencia_default_dias;
        this.vigenciaDias = res.vigencia_default_dias;
      },
      error: () => this.toastr.error('No se pudieron cargar los catálogos', 'Error'),
    });

    this.cargar(id);
  }

  private cargar(id: number): void {
    this.cargando = true;

    this.service.show(id).subscribe({
      next: (res) => {
        this.aplicar(res.data);
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo cargar la solicitud', 'Error');
        this.router.navigate(['/solicitudes-credito']);
      },
    });
  }

  /** Vuelca la respuesta del backend en el estado y el formulario. */
  private aplicar(data: SolicitudCreditoDetalle): void {
    this.solicitud = data;
    this.observaciones = data.evaluacion.observaciones ?? '';
    this.fechaPrimeraCuota = data.cronograma.fecha_primera_cuota ?? '';

    // Una solicitud ya resuelta no se vuelve a decidir: limpiar el
    // formulario evita que quede una decisión a medio escribir.
    if (!data.acciones.puede_decidir) {
      this.decision = null;
      this.motivo = '';
      this.detalleRechazo = '';
    }
  }

  // ---------- Acciones ----------

  tomarEnEvaluacion(): void {
    if (!this.solicitud || this.procesando) return;

    this.procesando = true;

    this.service.tomarEnEvaluacion(this.solicitud.scr_id).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Solicitud en evaluación');
        this.aplicar(res.data);
        this.procesando = false;
      },
      error: (err) => this.fallo(err, 'No se pudo tomar en evaluación'),
    });
  }

  guardarObservaciones(): void {
    if (!this.solicitud || this.procesando) return;

    this.procesando = true;

    this.service
      .guardarObservaciones(this.solicitud.scr_id, this.observaciones.trim() || null)
      .subscribe({
        next: (res) => {
          this.toastr.success(res.message, 'Observaciones');
          this.aplicar(res.data);
          this.procesando = false;
        },
        error: (err) => this.fallo(err, 'No se pudieron guardar las observaciones'),
      });
  }

  recalcularCronograma(): void {
    if (!this.solicitud || this.procesando || !this.fechaPrimeraCuota) return;

    this.procesando = true;

    this.service.fijarCronograma(this.solicitud.scr_id, this.fechaPrimeraCuota).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Cronograma');
        this.aplicar(res.data);
        this.procesando = false;
      },
      error: (err) => this.fallo(err, 'No se pudo recalcular el cronograma'),
    });
  }

  /** Los botones del Resumen llevan a Evaluación con la decisión ya elegida. */
  irADecidir(decision: 'APROBAR' | 'RECHAZAR'): void {
    this.decision = decision;
    this.pestana = 'evaluacion';
  }

  get puedeGuardarDecision(): boolean {
    if (!this.decision || this.procesando) return false;

    if (this.decision === 'APROBAR') {
      return !!this.vigenciaDias && this.vigenciaDias >= 1 && this.vigenciaDias <= 60;
    }

    if (!this.motivo) return false;

    // El motivo "Otro" no explica nada por sí solo.
    return this.motivo !== 'OTRO' || !!this.detalleRechazo.trim();
  }

  guardarDecision(): void {
    if (!this.solicitud || !this.puedeGuardarDecision) return;

    const payload: DecisionPayload = {
      decision: this.decision!,
      observaciones: this.observaciones.trim() || null,
    };

    if (this.decision === 'APROBAR') {
      payload.vigencia_dias = this.vigenciaDias!;
    } else {
      payload.motivo = this.motivo;
      payload.detalle = this.detalleRechazo.trim() || null;
    }

    this.procesando = true;

    this.service.decidir(this.solicitud.scr_id, payload).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Decisión registrada');
        this.aplicar(res.data);
        this.procesando = false;
      },
      error: (err) => this.fallo(err, 'No se pudo registrar la decisión'),
    });
  }

  // ---------- Modal de plan ----------

  abrirCambiarPlan(linea: DetalleSolicitudLinea): void {
    this.lineaEnEdicion = linea;
    this.modalPlanVisible = true;
  }

  onPlanGuardado(data: SolicitudCreditoDetalle): void {
    this.aplicar(data);
  }

  // ---------- Presentación ----------

  /** Cronograma firme si existe; si no, la estimación sin vencimientos. */
  get cronograma(): CuotaCronograma[] {
    if (!this.solicitud) return [];

    return this.solicitud.cronograma.cuotas.length > 0
      ? this.solicitud.cronograma.cuotas
      : this.solicitud.cronograma.estimado;
  }

  get cronogramaEsFirme(): boolean {
    return (this.solicitud?.cronograma.cuotas.length ?? 0) > 0;
  }

  /**
   * Semáforo de la relación cuota/ingreso. Los cortes son una referencia
   * visual para el evaluador, no una regla automática: la decisión sigue
   * siendo de una persona.
   */
  colorRelacion(porcentaje: number | null): string {
    if (porcentaje === null) return 'secondary';
    if (porcentaje <= 30) return 'success';
    if (porcentaje <= 45) return 'warning';
    return 'danger';
  }

  colorEstado(codigo: string | null): string {
    switch (codigo) {
      case 'APROBADA':    return 'success';
      case 'ACEPTADA':    return 'primary';
      case 'EN_REVISION': return 'info';
      case 'RECHAZADA':   return 'danger';
      case 'PENDIENTE':   return 'warning';
      default:            return 'secondary';
    }
  }

  textoTramo(desde: number, hasta: number): string {
    return desde === hasta ? `cuota ${desde}` : `cuotas ${desde} a ${hasta}`;
  }

    /**
   * El Resource envía el código de la columna (SOLTERO, UNION_LIBRE...).
   * La etiqueta legible se arma acá, igual que en la tienda.
   */
  etiquetaEstadoCivil(valor: string | null): string {
    const etiquetas: Record<string, string> = {
      SOLTERO: 'Soltero/a',
      CASADO: 'Casado/a',
      DIVORCIADO: 'Divorciado/a',
      VIUDO: 'Viudo/a',
      UNION_LIBRE: 'Unión libre',
    };
    return valor ? (etiquetas[valor] ?? valor) : '—';
  }

  etiquetaTipoVivienda(valor: string | null): string {
    if (!valor) return '—';
    return valor === 'PROPIA' ? 'Casa propia' : 'Alquiler';
  }

  volver(): void {
    this.router.navigate(['/solicitudes-credito']);
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }

  private fallo(err: any, titulo: string): void {
    this.procesando = false;

    const errores = err?.error?.errors;
    const mensaje = errores
      ? (Object.values(errores)[0] as string[])[0]
      : err?.error?.message ?? titulo;

    this.toastr.error(mensaje, titulo);
  }
}