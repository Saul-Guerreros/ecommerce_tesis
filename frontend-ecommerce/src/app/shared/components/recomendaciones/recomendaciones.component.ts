import { CommonModule, isPlatformBrowser } from '@angular/common';
import { Component, Input, OnChanges, PLATFORM_ID, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ProductoCardComponent } from '../producto-card/producto-card.component';
import { ProductoVitrina } from '../../../core/models/vitrina.model';
import {
  EstrategiaRecomendacion, RecomendacionResponse, TipoRecomendacion,
} from '../../../core/models/recomendacion.model';
import { RecomendacionService } from '../../../core/services/recomendacion.service';

/**
 * Carrusel de recomendaciones. Solo carga en el navegador: el perfil
 * depende del visitante (localStorage), no tiene sentido en SSR.
 */
@Component({
  selector: 'app-recomendaciones',
  standalone: true,
  imports: [CommonModule, ProductoCardComponent],
  templateUrl: './recomendaciones.component.html',
})
export class RecomendacionesComponent implements OnChanges {
  @Input() tipo: TipoRecomendacion = 'para-ti';
  @Input() slug: string | null = null;
  @Input() titulo = 'Recomendado para vos';
  @Input() limite = 8;

  productos: ProductoVitrina[] = [];
  estrategia: EstrategiaRecomendacion | null = null;

  private readonly servicio = inject(RecomendacionService);
  private readonly esNavegador = isPlatformBrowser(inject(PLATFORM_ID));

  private static readonly SUBTITULOS: Record<EstrategiaRecomendacion, string> = {
    personalizado: 'Según tus compras y los productos que consultaste',
    similares: 'Clientes que vieron este producto también se interesaron en',
    categoria: 'Más opciones de esta categoría',
    tendencias: 'Lo más elegido por nuestros clientes',
    novedades: 'Lo último que llegó a la tienda',
  };

  get subtitulo(): string {
    return this.estrategia ? RecomendacionesComponent.SUBTITULOS[this.estrategia] : '';
  }

  ngOnChanges(): void {
    if (!this.esNavegador) return;
    if (this.tipo === 'producto' && !this.slug) return;
    this.cargar();
  }

  trackByProducto(_: number, p: ProductoVitrina): number {
    return p.prd_id;
  }

  private cargar(): void {
    const peticion: Observable<RecomendacionResponse> =
      this.tipo === 'producto' ? this.servicio.similares(this.slug!, this.limite)
      : this.tipo === 'tendencias' ? this.servicio.tendencias(this.limite)
      : this.servicio.paraTi(this.limite);

    peticion.subscribe({
      next: (res) => {
        this.productos = res.data ?? [];
        this.estrategia = res.meta?.estrategia ?? null;
      },
      // Recomendar es un extra: si falla, la sección simplemente no aparece.
      error: () => (this.productos = []),
    });
  }
}