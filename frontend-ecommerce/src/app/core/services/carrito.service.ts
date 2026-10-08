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
  ModalidadPago,
} from '../models/carrito.model';

/** Lo único que se guarda en el navegador: NUNCA precios ni montos de cuota. */
interface ItemInvitado {
  car_idpro: number;
  car_cantid: number;
  car_modpag: ModalidadPago;
  car_numcuo: number | null;
}

export interface AgregarAlCarrito {
  car_idpro: number;
  car_idvarp?: number | null;
  car_cantid: number;
  car_modpag?: ModalidadPago;
  car_numcuo?: number | null;
}

const CLAVE_INVITADO = 'carrito_invitado';

@Injectable({ providedIn: 'root' })
export class CarritoService {

  private readonly carrito = new BehaviorSubject<CarritoItem[]>([]);

  readonly carrito$: Observable<CarritoItem[]> = this.carrito.asObservable();

  readonly cantidadItems$ = this.carrito$.pipe(map((items) => items.length));

  readonly cantidadUnidades$ = this.carrito$.pipe(
    map((items) => items.reduce((suma, i) => suma + Number(i.car_cantid), 0))
  );

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

  /**
   * La modalidad y el nº de cuotas forman parte de la identidad de la fila:
   * el mismo producto al contado y en 6 cuotas son DOS filas distintas.
   */
  private esMismaFila(a: CarritoItem, b: CarritoItem): boolean {
    if (a.car_id && b.car_id) {
      return a.car_id === b.car_id;
    }

    return (
      a.car_idpro === b.car_idpro &&
      (a.car_idvarp ?? null) === (b.car_idvarp ?? null) &&
      (a.car_modpag ?? 'contado') === (b.car_modpag ?? 'contado') &&
      (a.car_numcuo ?? null) === (b.car_numcuo ?? null)
    );
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

  agregar(datos: AgregarAlCarrito): Observable<CarritoItemResponse> {
    const modalidad: ModalidadPago = datos.car_modpag ?? 'contado';
    const numeroCuotas = modalidad === 'credito' ? (datos.car_numcuo ?? null) : null;

    if (this.authService.isLoggedIn()) {
      // store() responde 422 si esa combinación ya está: se traduce a un
      // PUT de cantidad acumulada para que "agregar otra vez" siga sumando.
      const existente = this.carrito.getValue().find(
        (i) =>
          i.car_idpro === datos.car_idpro &&
          (i.car_idvarp ?? null) === (datos.car_idvarp ?? null) &&
          (i.car_modpag ?? 'contado') === modalidad &&
          (i.car_numcuo ?? null) === numeroCuotas
      );

      if (existente) {
        return this.actualizarCantidad(
          existente,
          Number(existente.car_cantid) + datos.car_cantid
        );
      }

      return this.http
        .post<CarritoItemResponse>(`${URL_SERVICIOS}/tienda/carrito`, {
          car_idpro: datos.car_idpro,
          car_idvarp: datos.car_idvarp ?? null,
          car_cantid: datos.car_cantid,
          car_modpag: modalidad,
          car_numcuo: numeroCuotas,
        })
        .pipe(tap((resp) => this.cambiarItem(resp.data)));
    }

    // Invitado: PRIMERO se persiste en localStorage y DESPUÉS se previsualiza.
    const guardados = this.leerInvitado();
    const indice = guardados.findIndex(
      (i) =>
        i.car_idpro === datos.car_idpro &&
        i.car_modpag === modalidad &&
        (i.car_numcuo ?? null) === numeroCuotas
    );

    if (indice !== -1) {
      guardados[indice] = {
        ...guardados[indice],
        car_cantid: Number(guardados[indice].car_cantid) + datos.car_cantid,
      };
    } else {
      guardados.push({
        car_idpro: datos.car_idpro,
        car_cantid: datos.car_cantid,
        car_modpag: modalidad,
        car_numcuo: numeroCuotas,
      });
    }

    this.guardarInvitado(guardados);

    return this.previsualizarInvitado().pipe(
      map((resp): CarritoItemResponse => ({
        message: 'Producto agregado al carrito.',
        data: resp.data.find(
          (i) =>
            i.car_idpro === datos.car_idpro &&
            (i.car_modpag ?? 'contado') === modalidad &&
            (i.car_numcuo ?? null) === numeroCuotas
        )!,
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
      this.esMismoInvitado(i, item) ? { ...i, car_cantid } : i
    );
    this.guardarInvitado(guardados);

    return this.previsualizarInvitado().pipe(
      map((resp): CarritoItemResponse => ({
        message: 'Cantidad actualizada.',
        data: resp.data.find((i) => this.esMismaFila(i, item))!,
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
      this.leerInvitado().filter((i) => !this.esMismoInvitado(i, item))
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

  /**
   * Vacía solo una sección (Contado o Crédito), sin tocar la otra.
   * Usado por el botón "Vaciar Carrito Contado/Crédito" de la página del
   * carrito — vaciar() arriba sigue siendo el vaciado total (sin uso hoy
   * en esa página, pero se deja por si algún otro punto lo necesita).
   */
  vaciarModalidad(modalidad: ModalidadPago): Observable<CarritoMensajeResponse> {
    if (this.authService.isLoggedIn()) {
      return this.http
        .delete<CarritoMensajeResponse>(`${URL_SERVICIOS}/tienda/carrito/modalidad/${modalidad}`)
        .pipe(
          tap(() =>
            this.reiniciar(this.carrito.getValue().filter((i) => (i.car_modpag ?? 'contado') !== modalidad))
          )
        );
    }

    this.guardarInvitado(this.leerInvitado().filter((i) => i.car_modpag !== modalidad));
    this.reiniciar(this.carrito.getValue().filter((i) => (i.car_modpag ?? 'contado') !== modalidad));

    const mensaje = modalidad === 'contado' ? 'Carrito al contado vaciado.' : 'Carrito a crédito vaciado.';
    return of<CarritoMensajeResponse>({ message: mensaje });
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

  private esMismoInvitado(guardado: ItemInvitado, item: CarritoItem): boolean {
    return (
      guardado.car_idpro === item.car_idpro &&
      guardado.car_modpag === (item.car_modpag ?? 'contado') &&
      (guardado.car_numcuo ?? null) === (item.car_numcuo ?? null)
    );
  }

  private leerInvitado(): ItemInvitado[] {
    if (!this.esNavegador) return [];

    try {
      const crudos = JSON.parse(localStorage.getItem(CLAVE_INVITADO) ?? '[]');

      if (!Array.isArray(crudos)) return [];

      // Carritos guardados antes de esta funcionalidad no tienen modalidad:
      // se asumen al contado en vez de descartarlos.
      return crudos.map((i: any) => ({
        car_idpro: Number(i.car_idpro),
        car_cantid: Number(i.car_cantid),
        car_modpag: i.car_modpag === 'credito' ? 'credito' : 'contado',
        car_numcuo: i.car_numcuo ?? null,
      }));
    } catch {
      return [];
    }
  }

  private guardarInvitado(items: ItemInvitado[]): void {
    if (!this.esNavegador) return;
    localStorage.setItem(CLAVE_INVITADO, JSON.stringify(items));
  }
}