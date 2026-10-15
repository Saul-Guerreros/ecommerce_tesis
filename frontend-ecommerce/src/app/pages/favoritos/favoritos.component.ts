import { CommonModule } from '@angular/common';
import { Component, afterNextRender, effect, inject, signal, untracked } from '@angular/core';
import { RouterModule } from '@angular/router';
import { ProductoVitrina } from '../../core/models/vitrina.model';
import { FavoritoService } from '../../core/services/favorito.service';
import { ProductoCardComponent } from '../../shared/components/producto-card/producto-card.component';
import { AuthService } from '../auth/service/auth.service';

/**
 * Página "Mis favoritos". Pública a propósito: un invitado también puede ver
 * (y quitar) lo que guardó en este dispositivo; al iniciar sesión se fusiona
 * con su cuenta (ver FavoritoService).
 */
@Component({
  selector: 'app-favoritos',
  standalone: true,
  imports: [CommonModule, RouterModule, ProductoCardComponent],
  templateUrl: './favoritos.component.html',
  styleUrl: './favoritos.component.css',
})
export class FavoritosComponent {
  private readonly favoritos = inject(FavoritoService);
  readonly auth = inject(AuthService);

  readonly productos = signal<ProductoVitrina[]>([]);
  readonly cargando = signal(true);
  readonly error = signal(false);

  private cargaInicialHecha = false;

  constructor() {
    // Después del primer render, cuando AuthService ya leyó el token.
    afterNextRender(() => this.cargar());

    // Mantiene la grilla en sintonía con los corazones:
    //  - si quitás uno (desde la tarjeta), la tarjeta desaparece al instante;
    //  - si aparecen ids nuevos (p. ej. la fusión tras iniciar sesión), recarga.
    effect(
      () => {
        const ids = this.favoritos.ids();
        untracked(() => this.alCambiarIds(ids));
      },
      { allowSignalWrites: true },
    );
  }

  cargar(): void {
    this.cargando.set(true);
    this.error.set(false);

    this.favoritos.listar().subscribe({
      next: (lista) => {
        this.productos.set(lista);
        this.cargaInicialHecha = true;
        this.cargando.set(false);
      },
      error: () => {
        this.error.set(true);
        this.cargando.set(false);
      },
    });
  }

  private alCambiarIds(ids: number[]): void {
    if (!this.cargaInicialHecha) return;

    const vigentes = new Set(ids);
    const actuales = this.productos();
    const visibles = actuales.filter((p) => vigentes.has(p.prd_id));

    if (visibles.length !== actuales.length) {
      this.productos.set(visibles);
    }

    const mostrados = new Set(visibles.map((p) => p.prd_id));

    if (ids.some((id) => !mostrados.has(id))) {
      this.cargaInicialHecha = false; // evita reentradas mientras recarga
      this.favoritos.listar().subscribe({
        next: (lista) => {
          this.productos.set(lista);
          this.cargaInicialHecha = true;
        },
        error: () => (this.cargaInicialHecha = true),
      });
    }
  }
}