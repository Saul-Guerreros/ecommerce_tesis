import { isPlatformBrowser } from '@angular/common';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Inject, Injectable, PLATFORM_ID } from '@angular/core';
import { ToastrService } from 'ngx-toastr';
import { BehaviorSubject, Observable, map } from 'rxjs';
import { URL_BACKEND, URL_SERVICIOS } from '../../config/config';
import { ComparacionResponse, ItemComparador, ResultadoComparador } from '../models/comparador.model';
import { ProductoVitrina } from '../models/vitrina.model';

const CLAVE_STORAGE = 'z_comparador_v1';

/**
 * Lista de productos a comparar (patrón de los grandes e-commerce):
 *  - máximo 4 productos a la vez;
 *  - solo de la misma categoría (comparar un notebook con un mouse no sirve);
 *  - persiste en localStorage: sobrevive a navegar y a recargar, sin login;
 *  - es SSR-safe: en el servidor no existe localStorage, así que no toca nada.
 *
 * Solo se guardan slug + datos mínimos para la barra flotante. Los precios,
 * el stock y las especificaciones se piden SIEMPRE frescos al backend cuando
 * se abre la página de comparación (nunca se confía en lo guardado).
 */
@Injectable({ providedIn: 'root' })
export class ComparadorService {
  static readonly MAXIMO = 4;
  /** Poné false si querés permitir mezclar categorías. */
  static readonly SOLO_MISMA_CATEGORIA = true;

  private readonly esNavegador: boolean;
  private readonly estado = new BehaviorSubject<ItemComparador[]>([]);

  readonly items$: Observable<ItemComparador[]> = this.estado.asObservable();
  readonly cantidad$: Observable<number> = this.items$.pipe(map((i) => i.length));

  constructor(
    private http: HttpClient,
    private toastr: ToastrService,
    @Inject(PLATFORM_ID) platformId: Object,
  ) {
    this.esNavegador = isPlatformBrowser(platformId);

    if (this.esNavegador) {
      this.estado.next(this.leer());
      // Si el cliente tiene otra pestaña abierta, mantiene ambas sincronizadas.
      window.addEventListener('storage', (e) => {
        if (e.key === CLAVE_STORAGE) this.estado.next(this.leer());
      });
    }
  }

  // ---------- Lectura ----------

  get items(): ItemComparador[] {
    return this.estado.getValue();
  }

  get cantidad(): number {
    return this.items.length;
  }

  get llena(): boolean {
    return this.cantidad >= ComparadorService.MAXIMO;
  }

  get categoriaActual(): string | null {
    return this.items[0]?.categoria ?? null;
  }

  estaEnComparador(slug: string | null | undefined): boolean {
    return !!slug && this.items.some((i) => i.slug === slug);
  }

  // ---------- Acciones ----------

  /** Botón "Comparar": agrega si no está, quita si ya está. Avisa con un toast. */
  alternar(producto: Pick<ProductoVitrina, 'slug' | 'titulo' | 'imagen' | 'categoria'>): ResultadoComparador {
    if (!producto.slug) return { estado: 'rechazado' };

    if (this.estaEnComparador(producto.slug)) {
      this.quitar(producto.slug);
      this.toastr.info(producto.titulo, 'Quitado del comparador');
      return { estado: 'quitado' };
    }

    if (this.llena) {
      this.toastr.warning(
        `Podés comparar hasta ${ComparadorService.MAXIMO} productos. Quitá uno para agregar otro.`,
        'Comparador lleno',
      );
      return { estado: 'rechazado', motivo: 'limite' };
    }

    const categoria = producto.categoria ?? null;
    const actual = this.categoriaActual;
    if (ComparadorService.SOLO_MISMA_CATEGORIA && actual && categoria && actual !== categoria) {
      this.toastr.warning(
        `Solo se pueden comparar productos de la misma categoría (${actual}). Vaciá el comparador para empezar otra comparación.`,
        'Categoría distinta',
      );
      return { estado: 'rechazado', motivo: 'categoria', categoriaActual: actual };
    }

    this.guardar([
      ...this.items,
      { slug: producto.slug, titulo: producto.titulo, imagen: producto.imagen ?? null, categoria },
    ]);
    this.toastr.success(producto.titulo, 'Agregado para comparar');
    return { estado: 'agregado' };
  }

  quitar(slug: string): void {
    this.guardar(this.items.filter((i) => i.slug !== slug));
  }

  vaciar(): void {
    this.guardar([]);
  }

  /**
   * Tras pedir la comparación al backend: descarta los productos que ya no
   * están publicados y refresca título/imagen de los que sí.
   */
  sincronizar(vigentes: ProductoVitrina[]): void {
    const porSlug = new Map(vigentes.filter((p) => p.slug).map((p) => [p.slug as string, p]));

    this.guardar(
      this.items
        .filter((i) => porSlug.has(i.slug))
        .map((i) => {
          const p = porSlug.get(i.slug)!;
          return { slug: i.slug, titulo: p.titulo, imagen: p.imagen, categoria: p.categoria };
        }),
    );
  }

  // ---------- HTTP ----------

  /** Una sola petición para todos los productos (público, sin auth). */
  obtenerComparacion(slugs: string[]): Observable<ComparacionResponse> {
    const params = new HttpParams().set('slugs', slugs.join(','));
    return this.http.get<ComparacionResponse>(`${URL_SERVICIOS}/tienda/productos/comparar`, { params });
  }

  // ---------- Utilidades ----------

  /** Misma regla que usa la tarjeta de producto para armar la URL de la imagen. */
  static urlImagen(img: string | null | undefined): string {
    if (!img) return IMAGEN_FALLBACK;
    if (img.startsWith('http')) return img;
    return `${URL_BACKEND.replace(/\/$/, '')}/${img.replace(/^\//, '')}`;
  }

  // ---------- Persistencia ----------

  private guardar(items: ItemComparador[]): void {
    this.estado.next(items);
    if (!this.esNavegador) return;
    try {
      localStorage.setItem(CLAVE_STORAGE, JSON.stringify(items));
    } catch {
      /* modo privado / cuota llena: el comparador sigue funcionando en memoria */
    }
  }

  private leer(): ItemComparador[] {
    try {
      const crudo = JSON.parse(localStorage.getItem(CLAVE_STORAGE) ?? '[]');
      if (!Array.isArray(crudo)) return [];
      return crudo
        .filter((i) => i && typeof i.slug === 'string' && i.slug)
        .slice(0, ComparadorService.MAXIMO)
        .map((i) => ({
          slug: i.slug,
          titulo: String(i.titulo ?? ''),
          imagen: i.imagen ?? null,
          categoria: i.categoria ?? null,
        }));
    } catch {
      return [];
    }
  }
}

export const IMAGEN_FALLBACK =
  'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22300%22%20height%3D%22225%22%20viewBox%3D%220%200%20300%20225%22%3E%3Crect%20width%3D%22300%22%20height%3D%22225%22%20fill%3D%22%23e9e9e9%22/%3E%3Ctext%20x%3D%22150%22%20y%3D%22117%22%20font-family%3D%22Arial%2C%20sans-serif%22%20font-size%3D%2216%22%20fill%3D%22%23999%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3ESin%20imagen%3C/text%3E%3C/svg%3E';
