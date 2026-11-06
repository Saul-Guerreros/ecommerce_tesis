// modules/inventario-inteligente/cerrar-alerta/cerrar-alerta.component.ts
import { Component, EventEmitter, Input, OnChanges, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ButtonModule, FormModule, ModalModule } from '@coreui/angular';
import { AlertaStock, TIPOS_ALERTA } from '../models/inventario-inteligente.model';

export type ModoCierre = 'atender' | 'descartar';

/** Modal para atender (observación opcional) o descartar (motivo obligatorio). */
@Component({
  selector: 'app-cerrar-alerta',
  standalone: true,
  imports: [CommonModule, FormsModule, ButtonModule, ModalModule, FormModule],
  templateUrl: './cerrar-alerta.component.html',
})
export class CerrarAlertaComponent implements OnChanges {
  @Input() visible = false;
  @Input() alerta: AlertaStock | null = null;
  @Input() modo: ModoCierre = 'atender';

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() confirmar = new EventEmitter<string | null>();

  readonly TIPOS = TIPOS_ALERTA;
  observacion = '';

  ngOnChanges(): void {
    if (this.visible) this.observacion = '';
  }

  get valido(): boolean {
    return this.modo === 'atender' || this.observacion.trim().length >= 3;
  }

  enviar(): void {
    if (!this.valido) return;
    this.confirmar.emit(this.observacion.trim() || null);
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
