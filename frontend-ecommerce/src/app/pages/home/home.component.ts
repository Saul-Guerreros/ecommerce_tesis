import { Component, OnDestroy, afterNextRender } from '@angular/core';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [],
  templateUrl: './home.component.html',
  styleUrl: './home.component.css'
})
export class HomeComponent implements OnDestroy {

  private countdownTarget = 0;
  private countdownIntervalId: ReturnType<typeof setInterval> | null = null;

  constructor() {
    // Solo se ejecuta en el navegador (nunca en el servidor durante SSR)
    afterNextRender(() => {
      // 2 días desde que se carga la página (igual que en index3.html)
      this.countdownTarget = Date.now() + 2 * 24 * 60 * 60 * 1000;
      this.tickCountdown();
      this.countdownIntervalId = setInterval(() => this.tickCountdown(), 1000);
    });
  }

  ngOnDestroy(): void {
    if (this.countdownIntervalId !== null) {
      clearInterval(this.countdownIntervalId);
    }
  }

  private tickCountdown(): void {
    const el = document.getElementById('tm-deal-countdown');
    if (!el) return;

    const diff = Math.max(0, this.countdownTarget - Date.now());
    const days = Math.floor(diff / 86400000);
    const hours = Math.floor((diff % 86400000) / 3600000);
    const minutes = Math.floor((diff % 3600000) / 60000);
    const seconds = Math.floor((diff % 60000) / 1000);

    this.setUnit(el, 'days', days);
    this.setUnit(el, 'hours', hours);
    this.setUnit(el, 'minutes', minutes);
    this.setUnit(el, 'seconds', seconds);

    if (diff === 0 && this.countdownIntervalId !== null) {
      clearInterval(this.countdownIntervalId);
      this.countdownIntervalId = null;
    }
  }

  private setUnit(container: HTMLElement, unit: string, value: number): void {
    const target = container.querySelector(`[data-unit="${unit}"]`);
    if (target) {
      target.textContent = String(value).padStart(2, '0');
    }
  }
}
