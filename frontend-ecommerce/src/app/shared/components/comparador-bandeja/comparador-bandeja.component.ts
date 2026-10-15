import { CommonModule } from '@angular/common';
import { Component, inject } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { ComparadorService } from '../../../core/services/comparador.service';

/**
 * Barra flotante inferior: muestra qué productos se están comparando y
 * lleva a la página de comparación. Se oculta sola cuando no hay productos
 * o cuando ya estás en la página de comparación.
 *
 * ngSkipHydration: la lista vive en localStorage (solo existe en el
 * navegador), así que el servidor siempre la renderiza vacía. Se omite la
 * hidratación para que el cliente la dibuje de cero sin avisos de desajuste.
 */
@Component({
  selector: 'app-comparador-bandeja',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './comparador-bandeja.component.html',
  styleUrl: './comparador-bandeja.component.css',
  host: { ngSkipHydration: 'true' },
})
export class ComparadorBandejaComponent {
  readonly comparador = inject(ComparadorService);
  private readonly router = inject(Router);

  readonly urlImagen = ComparadorService.urlImagen;
  /** Casilleros vacíos hasta completar el máximo (2/4, 3/4...). */
  readonly huecos = Array.from({ length: ComparadorService.MAXIMO });

  get visible(): boolean {
    return this.comparador.cantidad > 0 && !this.router.url.startsWith('/comparar-productos');
  }

  irAComparar(): void {
    this.router.navigate(['/comparar-productos']);
  }
}
