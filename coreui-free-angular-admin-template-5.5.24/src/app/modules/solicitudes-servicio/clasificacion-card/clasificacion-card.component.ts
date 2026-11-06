import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { Clasificacion, ClasificacionConfig, CorreccionPayload } from '../models/clasificacion.model';
import { ClasificacionService } from '../service/clasificacion.service';

/**
 * Punto 7 - Tarjeta de clasificación automática (IA) de una solicitud.
 * Muestra tipo de problema, urgencia y dispositivo; permite confirmar, corregir y reclasificar.
 * La corrección es en línea (sin modal) para no anidar modales dentro del detalle.
 */
@Component({
  selector: 'app-clasificacion-card',
  standalone: true,
  imports: [CommonModule, FormsModule, BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule],
  templateUrl: './clasificacion-card.component.html',
})
export class ClasificacionCardComponent implements OnChanges {
  @Input({ required: true }) solicitudId!: number;
  /** Se corrigió o confirmó: quien contiene la tarjeta puede refrescarse. */
  @Output() actualizado = new EventEmitter<void>();

  clasificacion: Clasificacion | null = null;
  config: ClasificacionConfig | null = null;
  cargando = false;
  procesando = false;
  editando = false;
  form: CorreccionPayload & { motivo: string } = { tdi_id: 0, caf_id: 0, urg_id: 0, motivo: '' };

  constructor(private service: ClasificacionService, private toastr: ToastrService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['solicitudId'] && this.solicitudId) {
      this.editando = false;
      this.cargar();
    }
  }

  cargar(): void {
    this.cargando = true;
    this.service.show(this.solicitudId).subscribe({
      next: (res) => { this.clasificacion = res.data; this.cargando = false; },
      error: () => { this.cargando = false; this.toastr.error('No se pudo cargar la clasificación', 'Error'); },
    });
  }

  get generada(): boolean {
    return this.clasificacion?.estado === 'IA' || this.clasificacion?.estado === 'MANUAL';
  }

  get colorConfianza(): string {
    return { ALTA: 'success', MEDIA: 'warning', BAJA: 'danger' }[this.clasificacion?.confianza ?? 'BAJA'];
  }

  get etiquetaEstado(): { texto: string; color: string } {
    return {
      PENDIENTE: { texto: 'Clasificando...', color: 'info' },
      IA: { texto: 'IA', color: 'primary' },
      NO_DISPONIBLE: { texto: 'No disponible', color: 'secondary' },
      MANUAL: { texto: 'Corregida', color: 'dark' },
    }[this.clasificacion?.estado ?? 'NO_DISPONIBLE'];
  }

  abrirCorreccion(): void {
    this.service.getConfig().subscribe({
      next: (cfg) => {
        this.config = cfg;
        const c = this.clasificacion;
        this.form = {
          tdi_id: c?.dispositivo?.id ?? 0,
          caf_id: c?.categoria?.id ?? 0,
          urg_id: c?.urgencia?.id ?? 0,
          motivo: '',
        };
        this.editando = true;
      },
      error: () => this.toastr.error('No se pudieron cargar los catálogos', 'Error'),
    });
  }

  guardarCorreccion(): void {
    if (!this.form.tdi_id || !this.form.caf_id || !this.form.urg_id) {
      this.toastr.warning('Complete el dispositivo, el tipo de problema y la urgencia.', 'Validación');
      return;
    }
    this.procesando = true;
    this.service.corregir(this.solicitudId, { ...this.form, motivo: this.form.motivo.trim() || null }).subscribe({
      next: (res) => this.exito(res.message, res.data),
      error: (err) => this.fallo(err, 'No se pudo corregir la clasificación'),
    });
  }

  confirmar(): void {
    this.procesando = true;
    this.service.confirmar(this.solicitudId).subscribe({
      next: (res) => this.exito(res.message, res.data),
      error: (err) => this.fallo(err, 'No se pudo confirmar'),
    });
  }

  reclasificar(): void {
    this.procesando = true;
    this.service.reclasificar(this.solicitudId).subscribe({
      next: (res) => {
        this.procesando = false;
        this.toastr.info(res.message, 'Clasificación');
        this.cargar();
      },
      error: (err) => this.fallo(err, 'No se pudo reclasificar'),
    });
  }

  private exito(mensaje: string, data: Clasificacion): void {
    this.procesando = false;
    this.editando = false;
    this.clasificacion = data;
    this.toastr.success(mensaje, 'Éxito');
    this.actualizado.emit();
  }

  private fallo(err: any, porDefecto: string): void {
    this.procesando = false;
    const msg = err?.error?.errors ? Object.values(err.error.errors as Record<string, string[]>)[0]?.[0] : null;
    this.toastr.error(msg ?? err?.error?.message ?? porDefecto, 'Error');
  }
}