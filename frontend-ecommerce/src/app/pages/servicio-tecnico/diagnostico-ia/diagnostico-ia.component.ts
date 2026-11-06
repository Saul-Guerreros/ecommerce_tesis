import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, Input } from '@angular/core';
import { DiagnosticoIa, NivelIa } from '../../../core/models/servicio-tecnico.model';

/** Tarjeta del diagnóstico preliminar con IA (solo presentación). */
@Component({
  selector: 'app-diagnostico-ia',
  standalone: true,
  imports: [CommonModule, CurrencyPipe],
  templateUrl: './diagnostico-ia.component.html',
})
export class DiagnosticoIaComponent {
  @Input({ required: true }) diagnostico!: DiagnosticoIa;

  readonly etiqueta: Record<NivelIa, string> = { ALTA: 'Alta', MEDIA: 'Media', BAJA: 'Baja' };

  get claseConfianza(): string {
    const clases: Record<NivelIa, string> = { ALTA: 'uk-label-success', MEDIA: 'uk-label-warning', BAJA: 'uk-label-danger' };
    return clases[this.diagnostico.confianza ?? 'BAJA'];
  }
}