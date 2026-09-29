import { CommonModule, isPlatformBrowser } from '@angular/common';
import {
  Component,
  Inject,
  Input,
  OnChanges,
  OnDestroy,
  PLATFORM_ID,
  SimpleChanges,
} from '@angular/core';

/**
 * Cuenta regresiva reutilizable para campañas flash.
 *
 * A diferencia de un countdown "Días : Hrs : Min : Seg", este muestra el
 * total de horas restantes (puede pasar de 24) : minutos : segundos, para
 * que se lea directo "cuántas horas faltan" sin tener que sumar días + horas.
 *
 * Uso:
 *   <app-flash-countdown [fechaFin]="producto.oferta_fin"></app-flash-countdown>
 *
 * `fechaFin` es la fecha/hora de cierre de la oferta tal como la manda el
 * backend (campo `oferta_fin`), en cualquier formato que `new Date(...)`
 * pueda parsear.
 */
@Component({
  selector: 'app-flash-countdown',
  standalone: true,
  imports: [CommonModule],
  template: `
    <span class="fc-countdown" *ngIf="!expirado; else finalizado">
      <span class="fc-unit">{{ horas | number: '2.0-0' }}</span
      ><span class="fc-sep">:</span
      ><span class="fc-unit">{{ minutos | number: '2.0-0' }}</span
      ><span class="fc-sep">:</span
      ><span class="fc-unit">{{ segundos | number: '2.0-0' }}</span>
    </span>
    <ng-template #finalizado>
      <span class="fc-countdown fc-expirado">Offer ended</span>
    </ng-template>
  `,
  styles: [
    `
      .fc-countdown {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        letter-spacing: 0.02em;
      }
      .fc-sep {
        opacity: 0.6;
        padding: 0 2px;
      }
      .fc-expirado {
        font-weight: 500;
        opacity: 0.8;
      }
    `,
  ],
})
export class FlashCountdownComponent implements OnChanges, OnDestroy {
  @Input() fechaFin: string | null = null;

  horas = 0;
  minutos = 0;
  segundos = 0;
  expirado = false;

  private intervaloId: ReturnType<typeof setInterval> | null = null;

  constructor(@Inject(PLATFORM_ID) private platformId: Object) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['fechaFin']) {
      this.reiniciar();
    }
  }

  ngOnDestroy(): void {
    this.detener();
  }

  private reiniciar(): void {
    this.detener();

    if (!this.fechaFin) {
      this.expirado = true;
      return;
    }

    // Calculamos el valor inicial siempre (así el HTML que arma el servidor
    // ya muestra el número correcto), pero el temporizador que se repite
    // cada segundo SOLO se arranca en el navegador. Si lo dejamos correr
    // también en el servidor, el setInterval nunca termina y el SSR se
    // queda esperando para siempre a que la app se "estabilice".
    this.actualizar();

    if (isPlatformBrowser(this.platformId) && !this.expirado) {
      this.intervaloId = setInterval(() => this.actualizar(), 1000);
    }
  }

  private actualizar(): void {
    if (!this.fechaFin) {
      this.expirado = true;
      return;
    }

    const finMs = new Date(this.fechaFin).getTime();
    if (Number.isNaN(finMs)) {
      // Fecha no parseable: no rompemos la vista, solo mostramos "finalizado".
      this.expirado = true;
      this.detener();
      return;
    }

    const restanteMs = finMs - Date.now();

    if (restanteMs <= 0) {
      this.horas = 0;
      this.minutos = 0;
      this.segundos = 0;
      this.expirado = true;
      this.detener();
      return;
    }

    this.expirado = false;
    const totalSegundos = Math.floor(restanteMs / 1000);
    this.horas = Math.floor(totalSegundos / 3600);
    this.minutos = Math.floor((totalSegundos % 3600) / 60);
    this.segundos = totalSegundos % 60;
  }

  private detener(): void {
    if (this.intervaloId !== null) {
      clearInterval(this.intervaloId);
      this.intervaloId = null;
    }
  }
}
