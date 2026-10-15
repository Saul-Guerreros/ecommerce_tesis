import { isPlatformBrowser } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import {
  Injectable,
  PLATFORM_ID,
  afterNextRender,
  computed,
  effect,
  inject,
  signal,
  untracked,
} from '@angular/core';
import { Observable, catchError, finalize, map, of, tap, throwError } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { AuthService } from '../../pages/auth/service/auth.service';
import { ProductoVitrina } from '../models/vitrina.model';

/** Debe coincidir con FavoritoController::LIMITE del backend. */
export const LIMITE_FAVORITOS = 100;

const CLAVE_INVITADO = 'favoritos_invitado';

/**
 * Lista de favoritos (wishlist).
 *
 * - Con sesión: vive en la base de datos (cuenta del cliente, multi-dispositivo).
 * - Invitado: vive en localStorage (solo ids, nunca precios) y se FUSIONA con la
 *   cuenta apenas inicia sesión. Así nadie tiene que registrarse para guardar.
 *
 * El estado es un signal de ids: cualquier corazón de la tienda (tarjetas,
 * detalle, header) se actualiza solo, sin suscripciones manuales.
 * El cambio se aplica al instante (optimista) y se revierte si el servidor falla.
 */
@Injectable({ providedIn: 'root' })
export class FavoritoService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  private readonly esNavegador = isPlatformBrowser(inject(PLATFORM_ID));

  /** Ids de producto, el más reciente primero. */
  private readonly _ids = signal<number[]>([]);
  readonly ids = this._ids.asReadonly();
  readonly cantidad = computed(() => this._ids().length);

  private readonly conjunto = computed(() => new Set(this._ids()));
  private readonly enCurso = signal<ReadonlySet<number>>(new Set());
  private readonly navegadorListo = signal(false);

  constructor() {
    // Hasta que AuthService termina de leer el token (afterNextRender) no se
    // sabe si hay sesión; esperar evita tratar a un cliente logueado como invitado.
    afterNextRender(() => this.navegadorListo.set(true));

    // Se reacciona a CUALQUIER cambio de sesión (login normal, Google, modal del
    // carrito, logout, expiración, otra pestaña) sin tocar esos componentes.
    effect(
      () => {
        if (!this.navegadorListo()) return;
        const conSesion = this.auth.sesion();
        untracked(() => this.alCambiarSesion(conSesion));
      },
      { allowSignalWrites: true },
    );
  }

  // ---------- Consulta ----------

  es(productoId: number): boolean {
    return this.conjunto().has(productoId);
  }

  /** Hay una petición en curso para este producto (evita doble clic). */
  pendiente(productoId: number): boolean {
    return this.enCurso().has(productoId);
  }

  /** Productos completos para la página /favoritos. */
  listar(): Observable<ProductoVitrina[]> {
    if (this.auth.isLoggedIn()) {
      return this.http
        .get<{ data: ProductoVitrina[] }>(`${URL_SERVICIOS}/tienda/favoritos`)
        .pipe(
          map((resp) => resp.data),
          tap((lista) => this._ids.set(lista.map((p) => p.prd_id))),
        );
    }

    const locales = this.leerInvitado();

    if (locales.length === 0) {
      this._ids.set([]);
      return of([]);
    }

    return this.http
      .post<{ data: ProductoVitrina[] }>(`${URL_SERVICIOS}/tienda/favoritos/previsualizar`, { ids: locales })
      .pipe(
        map((resp) => resp.data),
        tap((lista) => {
          // Descarta del navegador los productos que ya no existen o se despublicaron.
          this._ids.set(lista.map((p) => p.prd_id));
          this.guardarInvitado(this._ids());
        }),
      );
  }

  // ---------- Escritura ----------

  /** Agrega o quita. Emite el estado final (true = quedó en favoritos). */
  alternar(productoId: number): Observable<boolean> {
    if (this.pendiente(productoId)) {
      return of(this.es(productoId));
    }

    const eraFavorito = this.es(productoId);
    const sera = !eraFavorito;

    if (sera && this._ids().length >= LIMITE_FAVORITOS) {
      return throwError(() => ({
        error: { message: `Podés guardar hasta ${LIMITE_FAVORITOS} favoritos. Quitá alguno para agregar otro.` },
      }));
    }

    // Optimista: el corazón cambia ya, sin esperar al servidor.
    this.fijar(productoId, sera);

    if (!this.auth.isLoggedIn()) {
      this.guardarInvitado(this._ids());
      return of(sera);
    }

    this.marcarPendiente(productoId, true);

    const peticion = sera
      ? this.http.post(`${URL_SERVICIOS}/tienda/favoritos`, { prd_id: productoId })
      : this.http.delete(`${URL_SERVICIOS}/tienda/favoritos/${productoId}`);

    return peticion.pipe(
      map(() => sera),
      catchError((err) => {
        this.fijar(productoId, eraFavorito); // revierte
        return throwError(() => err);
      }),
      finalize(() => this.marcarPendiente(productoId, false)),
    );
  }

  // ---------- Sesión ----------

  private alCambiarSesion(conSesion: boolean): void {
    if (!this.esNavegador) return;

    if (!conSesion) {
      // Invitado (o se cerró la sesión): la lista es la del navegador.
      this._ids.set(this.leerInvitado());
      return;
    }

    const locales = this.leerInvitado();

    if (locales.length === 0) {
      this.cargarIdsDelServidor();
      return;
    }

    // Recién inició sesión con favoritos de invitado: se fusionan con la cuenta.
    this.http
      .post<{ data: number[] }>(`${URL_SERVICIOS}/tienda/favoritos/sincronizar`, { ids: locales })
      .subscribe({
        next: (resp) => {
          this.guardarInvitado([]);
          this._ids.set(resp.data);
        },
        // Si falla, los locales se conservan y se reintenta en el próximo inicio.
        error: () => this.cargarIdsDelServidor(),
      });
  }

  private cargarIdsDelServidor(): void {
    this.http.get<{ data: number[] }>(`${URL_SERVICIOS}/tienda/favoritos/ids`).subscribe({
      next: (resp) => this._ids.set(resp.data),
      error: () => this._ids.set([]),
    });
  }

  // ---------- Estado interno ----------

  private fijar(productoId: number, activo: boolean): void {
    this._ids.update((lista) =>
      activo ? [productoId, ...lista.filter((i) => i !== productoId)] : lista.filter((i) => i !== productoId),
    );
  }

  private marcarPendiente(productoId: number, activo: boolean): void {
    this.enCurso.update((actual) => {
      const copia = new Set(actual);
      activo ? copia.add(productoId) : copia.delete(productoId);
      return copia;
    });
  }

  // ---------- localStorage (solo invitados) ----------

  private leerInvitado(): number[] {
    if (!this.esNavegador) return [];

    try {
      const crudos = JSON.parse(localStorage.getItem(CLAVE_INVITADO) ?? '[]');
      if (!Array.isArray(crudos)) return [];

      const ids = crudos.map(Number).filter((n) => Number.isInteger(n) && n > 0);
      return [...new Set(ids)].slice(0, LIMITE_FAVORITOS);
    } catch {
      return [];
    }
  }

  private guardarInvitado(ids: number[]): void {
    if (!this.esNavegador) return;

    try {
      localStorage.setItem(CLAVE_INVITADO, JSON.stringify(ids));
    } catch {
      /* almacenamiento lleno o bloqueado: el corazón igual funciona en esta sesión */
    }
  }
}