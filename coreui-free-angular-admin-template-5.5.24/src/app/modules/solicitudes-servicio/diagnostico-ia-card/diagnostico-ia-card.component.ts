import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BadgeModule, CardModule } from '@coreui/angular';
import { DiagnosticoIa } from '../models/diagnostico-ia.model';

/** Tarjeta del diagnóstico IA: la usan el mostrador y la orden del técnico. */
@Component({
  selector: 'app-diagnostico-ia-card',
  standalone: true,
  imports: [CommonModule, BadgeModule, CardModule],
  templateUrl: './diagnostico-ia-card.component.html',
})
export class DiagnosticoIaCardComponent {
  @Input({ required: true }) diagnostico!: DiagnosticoIa;
  @Input() titulo = 'Diagnóstico preliminar (IA)';
  @Input() mostrarCoincidencia = false;

  get colorConfianza(): string {
    return { ALTA: 'success', MEDIA: 'warning', BAJA: 'danger' }[this.diagnostico.confianza ?? 'BAJA'];
  }
}