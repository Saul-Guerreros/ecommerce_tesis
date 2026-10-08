import { CommonModule, DOCUMENT, isPlatformBrowser } from '@angular/common';
import {
  Component, Inject, Input, OnChanges, OnDestroy, PLATFORM_ID, Renderer2, SimpleChanges,
} from '@angular/core';

/**
 * Aviso de "búsqueda procesándose con IA".
 *
 * modo 'pantalla': capa a pantalla completa que difumina el fondo. Se usa
 *   cuando el cliente llega desde el buscador del header: así no ve una
 *   página vacía ni piensa que falló la conexión.
 * modo 'en-linea': mismo aviso dentro del área de resultados, sin bloquear
 *   la página. Se usa mientras escribe en el buscador de la propia página,
 *   para no taparle el campo de texto.
 *
 * Los mensajes cambian con el tiempo para que una espera larga no parezca
 * un cuelgue.
 */
@Component({
  selector: 'app-cargando-ia',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './cargando-ia.component.html',
  styleUrl: './cargando-ia.component.css',
})
export class CargandoIaComponent implements OnChanges, OnDestroy {
  @Input() visible = false;
  @Input() modo: 'pantalla' | 'en-linea' = 'pantalla';

  /** Texto secundario según cuánto lleva esperando. */
  detalle = 'Estamos interpretando lo que buscás para mostrarte los productos más adecuados.';

  private temporizadores: ReturnType<typeof setTimeout>[] = [];
  private readonly esNavegador: boolean;

  constructor(
    private renderer: Renderer2,
    @Inject(DOCUMENT) private documento: Document,
    @Inject(PLATFORM_ID) plataforma: object,
  ) {
    this.esNavegador = isPlatformBrowser(plataforma);
  }

  ngOnChanges(cambios: SimpleChanges): void {
    if (!cambios['visible'] && !cambios['modo']) return;

    this.limpiarTemporizadores();
    this.bloquearScroll(this.visible && this.modo === 'pantalla');

    if (this.visible) {
      this.detalle = 'Estamos interpretando lo que buscás para mostrarte los productos más adecuados.';
      this.programar(6000, 'Está tardando un poco más de lo normal. Ya casi está…');
      this.programar(12000, 'Seguimos trabajando en tu búsqueda, gracias por esperar.');
    }
  }

  ngOnDestroy(): void {
    this.limpiarTemporizadores();
    this.bloquearScroll(false);
  }

  private programar(ms: number, texto: string): void {
    if (!this.esNavegador) return;
    this.temporizadores.push(setTimeout(() => (this.detalle = texto), ms));
  }

  private limpiarTemporizadores(): void {
    this.temporizadores.forEach(clearTimeout);
    this.temporizadores = [];
  }

  /** Evita que la página de fondo se desplace mientras la capa está abierta. */
  private bloquearScroll(bloquear: boolean): void {
    if (!this.esNavegador) return;
    if (bloquear) {
      this.renderer.setStyle(this.documento.body, 'overflow', 'hidden');
    } else {
      this.renderer.removeStyle(this.documento.body, 'overflow');
    }
  }
}
