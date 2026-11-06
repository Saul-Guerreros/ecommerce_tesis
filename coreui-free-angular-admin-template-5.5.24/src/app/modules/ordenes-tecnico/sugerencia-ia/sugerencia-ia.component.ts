import { Component, Input, OnChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ButtonModule } from '@coreui/angular';
import { DiagnosticoIa } from '../../solicitudes-servicio/models/diagnostico-ia.model';
import { DiagnosticoIaCardComponent } from '../../solicitudes-servicio/diagnostico-ia-card/diagnostico-ia-card.component';
import { OrdenesTecnicoService } from '../service/ordenes-tecnico.service';
import { ClasificacionCardComponent } from '../../solicitudes-servicio/clasificacion-card/clasificacion-card.component';
/**
 * Sugerencia de la IA como REFERENCIA del técnico. Colapsada por defecto y sin precargar
 * el formulario: evita el sesgo de anclaje y mantiene válida la métrica de coincidencia.
 */
@Component({
  selector: 'app-sugerencia-ia',
  standalone: true,
  imports: [CommonModule, ButtonModule, DiagnosticoIaCardComponent, ClasificacionCardComponent],
  templateUrl: './sugerencia-ia.component.html',
})
export class SugerenciaIaComponent implements OnChanges {
  @Input({ required: true }) sseId!: number;

  diagnostico: DiagnosticoIa | null = null;
  visible = false;
 verClasificacion = false;

  constructor(private service: OrdenesTecnicoService) {}

  ngOnChanges(): void {
    this.service.diagnosticoPreliminar(this.sseId).subscribe({
      next: (res) => (this.diagnostico = res.data),
      error: () => (this.diagnostico = null),
    });
  }
}