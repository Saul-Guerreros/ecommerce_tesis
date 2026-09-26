import { isPlatformBrowser } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Inject, Injectable, PLATFORM_ID } from '@angular/core';
import { BehaviorSubject, Observable, map, of, tap } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { AuthService } from '../../pages/auth/service/auth.service';
import {
  CarritoItem,
  CarritoItemResponse,
  CarritoListResponse,
  CarritoMensajeResponse,
} from '../models/carrito.model';

/** Lo único que se guarda en el navegador del invitado: nunca precios. */
interface ItemInvitado {
  car_idpro: number;
  car_cantid: number;
}

const CLAVE_INVITADO = 'carrito_invitado';

@Injectable({ providedIn: 'root' })
export class CarritoService {

  // ---------- Estado reactivo (patrón Observer) ----------
  // BehaviorSubject y no Subject: todo componente que se suscriba después
  // (ej. el header al navegar) recibe de inmediato el último valor emitido.
  private readonly carrito = new BehaviorSubject<CarritoItem[]>([]);

  /** Único canal de lectura público. Nadie fuera del service llama a next(). */
  readonly carrito$: Observable<CarritoItem[]> = this.carrito.asObservable();

  /** Derivados: evitan que cada componente recalcule lo mismo. */
  readonly cantidadItems$ = this.carrito$.pipe(map((items) => items.length));
  readonly totalCarrito$ = this.carrito$.pipe(
    map((items) => items.reduce((suma, i) => suma + Number(i.car_total), 0))
  );

  private readonly esNavegador: boolean;

  constructor(
    private http: HttpClient,
    private authService: AuthService,
    @Inject(PLATFORM_ID) platformId: Object
  ) {
    this.esNavegador = isPlatformBrowser(platformId);
  }

  // ---------- Mutadores del estado en memoria ----------
  // Equivalen a changeCart/removeCart/resetCart del curso, con dos correcciones:
  //   1) se emite un ARRAY NUEVO (el curso muta el array y reemite la misma
  //      referencia, lo que rompe la detección de cambios con OnPush);
  //   2) la identidad de la fila contempla producto + variación, no solo el id
  //      (el invitado no tiene car_id porque su carrito no está persistido).

  cambiarItem(item: CarritoItem): void {
    const lista = [...this.carrito.getValue()];
    const indice = lista.findIndex((i) => this.esMismaFila(i, item));

    if (indice !== -1) {
      lista[indice] = item;
    } else {
      lista.unshift(item);
    }

    this.carrito.next(lista);
  }

  quitarItem(item: CarritoItem): void {
    this.carrito.next(
      this.carrito.getValue().filter((i) => !this.esMismaFila(i, item))
    );
  }

  reiniciar(items: CarritoItem[] = []): void {
    this.carrito.next(items);
  }

  private esMismaFila(a: CarritoItem, b: CarritoItem): boolean {
    if (a.car_id && b.car_id) {
      return a.car_id === b.car_id;
    }
    return a.car_idpro === b.car_idpro
        && (a.car_idvarp ?? null) === (b.car_idvarp ?? null);
  }

  // ---------- Lectura ----------

  cargar(): Observable<CarritoListResponse> {
    if (this.authService.isLoggedIn()) {
      return this.http
        .get<CarritoListResponse>(`${URL_SERVICIOS}/tienda/carrito`)
        .pipe(tap((resp) => this.reiniciar(resp.data)));
    }
    return this.previsualizarInvitado();
  }

  /** Resuelve contra el backend los ids que el invitado tiene en localStorage. */
  private previsualizarInvitado(): Observable<CarritoListResponse> {
    const guardados = this.leerInvitado();

    if (guardados.length === 0) {
      this.reiniciar([]);
      return of<CarritoListResponse>({ data: [] });
    }

    return this.http
      .post<CarritoListResponse>(
        `${URL_SERVICIOS}/tienda/carrito/previsualizar`,
        { items: guardados }
      )
      .pipe(tap((resp) => this.reiniciar(resp.data)));
  }

  // ---------- Escritura ----------

  agregar(datos: {
    car_idpro: number;
    car_idvarp?: number | null;
    car_cantid: number;
  }): Observable<CarritoItemResponse> {

    if (this.authService.isLoggedIn()) {
      return this.http
        .post<CarritoItemResponse>(`${URL_SERVICIOS}/tienda/carrito`, datos)
        .pipe(tap((resp) => this.cambiarItem(resp.data)));
    }

    const guardados = this.leerInvitado();
    const existente = guardados.find((i) => i.car_idpro === datos.car_idpro);

    if (existente) {
      existente.car_cantid += datos.car_cantid;
    } else {
      guardados.unshift({
        car_idpro: datos.car_idpro,
        car_cantid: datos.car_cantid,
      });
    }

    this.guardarInvitado(guardados);

    return this.previsualizarInvitado().pipe(
      map((resp): CarritoItemResponse => ({
        message: 'Producto agregado al carrito.',
        data: resp.data.find((i) => i.car_idpro === datos.car_idpro)!,
      }))
    );
  }

  actualizarCantidad(
    item: CarritoItem,
    car_cantid: number
  ): Observable<CarritoItemResponse> {

    if (this.authService.isLoggedIn()) {
      return this.http
        .put<CarritoItemResponse>(
          `${URL_SERVICIOS}/tienda/carrito/${item.car_id}`,
          { car_cantid }
        )
        .pipe(tap((resp) => this.cambiarItem(resp.data)));
    }

    const guardados = this.leerInvitado().map((i) =>
      i.car_idpro === item.car_idpro ? { ...i, car_cantid } : i
    );
    this.guardarInvitado(guardados);

    return this.previsualizarInvitado().pipe(
      map((resp): CarritoItemResponse => ({
        message: 'Cantidad actualizada.',
        data: resp.data.find((i) => i.car_idpro === item.car_idpro)!,
      }))
    );
  }

  eliminar(item: CarritoItem): Observable<CarritoMensajeResponse> {
    if (this.authService.isLoggedIn()) {
      return this.http
        .delete<CarritoMensajeResponse>(
          `${URL_SERVICIOS}/tienda/carrito/${item.car_id}`
        )
        .pipe(tap(() => this.quitarItem(item)));
    }

    this.guardarInvitado(
      this.leerInvitado().filter((i) => i.car_idpro !== item.car_idpro)
    );
    this.quitarItem(item);

    return of<CarritoMensajeResponse>({
      message: 'Producto eliminado del carrito.',
    });
  }

  vaciar(): Observable<CarritoMensajeResponse> {
    if (this.authService.isLoggedIn()) {
      return this.http
        .delete<CarritoMensajeResponse>(`${URL_SERVICIOS}/tienda/carrito`)
        .pipe(tap(() => this.reiniciar()));
    }

    this.guardarInvitado([]);
    this.reiniciar();

    return of<CarritoMensajeResponse>({ message: 'Carrito vaciado.' });
  }

  // ---------- Fusión al iniciar sesión ----------

  sincronizarTrasLogin(): Observable<any> {
    const guardados = this.leerInvitado();

    if (guardados.length === 0) {
      return this.cargar();
    }

    return this.http
      .post<any>(`${URL_SERVICIOS}/tienda/carrito/sincronizar`, {
        items: guardados,
      })
      .pipe(
        tap((resp) => {
          this.guardarInvitado([]);
          this.reiniciar(resp.data);
        })
      );
  }

  // ---------- localStorage ----------

  private leerInvitado(): ItemInvitado[] {
    if (!this.esNavegador) return [];
    try {
      return JSON.parse(localStorage.getItem(CLAVE_INVITADO) ?? '[]');
    } catch {
      return [];
    }
  }

  private guardarInvitado(items: ItemInvitado[]): void {
    if (!this.esNavegador) return;
    localStorage.setItem(CLAVE_INVITADO, JSON.stringify(items));
  }
}