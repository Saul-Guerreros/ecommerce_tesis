import { CommonModule } from '@angular/common';
import { Component, DestroyRef, ElementRef, HostListener, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Subject, catchError, debounceTime, distinctUntilChanged, map, of, switchMap, tap } from 'rxjs';
import { URL_BACKEND } from '../../../config/config';
import { SugerenciaProducto, SugerenciasResponse } from '../../../core/models/filtro-productos.model';
import { FiltroProductosService } from '../../../core/services/filtro-productos.service';
import { MonedaPyPipe } from '../../pipes/moneda-py.pipe';

/**
 * Buscador del header con resultados instantáneos: muestra productos
 * mientras el cliente escribe, sin necesidad de presionar Enter.
 *
 * Flujo (RxJS):
 *   tipeo -> debounceTime (espera una pausa) -> distinctUntilChanged
 *         -> switchMap (cancela la petición anterior si sigue escribiendo)
 *         -> GET /tienda/productos/sugerencias
 *
 * Enter (o "Ver todos") lleva a la página completa de resultados.
 * Teclado: ↑ ↓ para moverse, Enter para abrir, Esc para cerrar.
 */
@Component({
  selector: 'app-buscador-sugerencias',
  standalone: true,
  imports: [CommonModule, FormsModule, MonedaPyPipe],
  templateUrl: './buscador-sugerencias.component.html',
  styleUrl: './buscador-sugerencias.component.css',
})
export class BuscadorSugerenciasComponent {
  /** Caracteres mínimos para empezar a buscar. */
  private readonly MINIMO = 2;
  /** Pausa de tipeo antes de consultar (ms). */
  private readonly PAUSA = 250;

  consulta = '';
  resultados: SugerenciaProducto[] = [];
  total = 0;
  consultaCorregida: string | null = null;
  cargando = false;
  abierto = false;
  /** Índice resaltado con el teclado (-1 = ninguno). */
  activo = -1;

  private tipeo$ = new Subject<string>();
  private destroyRef = inject(DestroyRef);

  constructor(
    private filtroService: FiltroProductosService,
    private router: Router,
    private elemento: ElementRef<HTMLElement>,
  ) {
    this.tipeo$
      .pipe(
        map((texto) => texto.trim()),
        tap((texto) => {
          if (texto.length < this.MINIMO) this.limpiarResultados();
        }),
        debounceTime(this.PAUSA),
        distinctUntilChanged(),
        switchMap((texto) => {
          if (texto.length < this.MINIMO) return of(null);
          this.cargando = true;
          return this.filtroService.sugerencias(texto).pipe(catchError(() => of(null)));
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe((resp) => this.mostrar(resp));
  }

  onTipeo(): void {
    this.activo = -1;
    this.tipeo$.next(this.consulta);
  }

  get mostrarPanel(): boolean {
    return this.abierto && this.consulta.trim().length >= this.MINIMO;
  }

  // ---- Teclado ----

  onTecla(evento: KeyboardEvent): void {
    switch (evento.key) {
      case 'ArrowDown':
        evento.preventDefault();
        this.abierto = true;
        this.activo = Math.min(this.activo + 1, this.resultados.length - 1);
        break;
      case 'ArrowUp':
        evento.preventDefault();
        this.activo = Math.max(this.activo - 1, -1);
        break;
      case 'Enter':
        evento.preventDefault();
        if (this.activo >= 0 && this.resultados[this.activo]) {
          this.abrirProducto(this.resultados[this.activo]);
        } else {
          this.verTodos();
        }
        break;
      case 'Escape':
        this.abierto = false;
        this.activo = -1;
        break;
    }
  }

  /** Cierra el panel al hacer clic fuera del buscador. */
  @HostListener('document:click', ['$event'])
  onClickFuera(evento: MouseEvent): void {
    if (!this.elemento.nativeElement.contains(evento.target as Node)) {
      this.abierto = false;
    }
  }

  // ---- Navegación ----

  abrirProducto(producto: SugerenciaProducto): void {
    if (!producto.slug) return;
    this.cerrarTodo();
    this.router.navigate(['/producto', producto.slug]);
  }

  verTodos(): void {
    const texto = this.consulta.trim();
    if (!texto) return;
    this.cerrarTodo();
    this.router.navigate(['/productos-busqueda'], { queryParams: { search: texto } });
  }

  imagen(producto: SugerenciaProducto): string | null {
    if (!producto.imagen) return null;
    if (producto.imagen.startsWith('http')) return producto.imagen;
    return `${URL_BACKEND.replace(/\/$/, '')}/${producto.imagen.replace(/^\//, '')}`;
  }

  trackById(_i: number, p: SugerenciaProducto): number {
    return p.pub_id;
  }

  // ---- Internos ----

  private mostrar(resp: SugerenciasResponse | null): void {
    this.cargando = false;
    this.resultados = resp?.data ?? [];
    this.total = resp?.meta.total ?? 0;
    this.consultaCorregida = resp?.meta.consulta_corregida ?? null;
    this.abierto = true;
  }

  private limpiarResultados(): void {
    this.resultados = [];
    this.total = 0;
    this.consultaCorregida = null;
    this.cargando = false;
  }

  /** Cierra el panel y el desplegable de UIkit que contiene al buscador. */
  private cerrarTodo(): void {
    this.abierto = false;
    this.activo = -1;

    const drop = this.elemento.nativeElement.closest('[uk-drop]');
    const uikit = (window as any).UIkit;
    if (drop && uikit?.drop) {
      uikit.drop(drop)?.hide(false);
    }
  }
}
