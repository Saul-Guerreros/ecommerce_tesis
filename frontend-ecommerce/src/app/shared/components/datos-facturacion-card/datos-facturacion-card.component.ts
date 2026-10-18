import { CommonModule } from '@angular/common';
import { Component, Input } from '@angular/core';
import { FacturacionResumenCard } from '../../../core/models/resumen-compra.model';

/**
 * Tarjeta "Datos de Facturación". Sólo aparece desde el Paso 3 en
 * adelante: es el último dato que el cliente completa en el checkout
 * (Paso 2), así que recién tiene sentido resumirlo una vez que ya quedó
 * guardado contra un pedido confirmado.
 */
@Component({
  selector: 'app-datos-facturacion-card',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './datos-facturacion-card.component.html',
  styleUrl: './datos-facturacion-card.component.css',
})
export class DatosFacturacionCardComponent {
  @Input() facturacion: FacturacionResumenCard | null = null;
}