import { HttpClient } from '@angular/common/http';
import { Injectable, NgZone, afterNextRender, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, catchError, finalize, map, of, shareReplay } from 'rxjs';
import { URL_SERVICIOS } from '../../../config/config';

/** Se renueva el token cuando le quedan menos de 2 minutos. */
const MARGEN_RENOVACION_MS = 2 * 60 * 1000;
/** Variación aleatoria para que varias pestañas no renueven al mismo tiempo. */
const VARIACION_MAX_MS = 20 * 1000;
/** Sin interacción durante este tiempo, la sesión no se renueva. */
const VENTANA_INACTIVIDAD_MS = 30 * 60 * 1000;
/** Cada cuánto se guarda la actividad en localStorage (no en cada clic). */
const INTERVALO_GUARDADO_MS = 30 * 1000;
/** setTimeout no admite esperas mayores (~24,8 días). */
const MAX_TIMEOUT_MS = 2_147_483_647;

const CLAVE_TOKEN = 'token';
const CLAVE_USUARIO = 'user';
const CLAVE_ACTIVIDAD = 'ultima_actividad';

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  token = '';
  user: any;

  // Signal de estado de sesión: el header se refresca solo.
  readonly sesion = signal(false);

  private temporizador?: ReturnType<typeof setTimeout>;
  /** Renovación en curso compartida: evita pedir dos tokens a la vez. */
  private renovacion$?: Observable<boolean>;
  private ultimaActividad = 0;
  private ultimoGuardado = 0;

  constructor(
    public http: HttpClient,
    public router: Router,
    private zone: NgZone,
  ) {
    // Solo en el navegador (compatible con SSR)
    afterNextRender(() => {
      this.initAuth();
      this.escucharActividad();
      this.escucharOtrasPestanas();
    });
  }

  // ------------------------------------------------------------------
  // Inicio de sesión
  // ------------------------------------------------------------------

  initAuth(): void {
    this.cargarDesdeAlmacenamiento();

    // Token vencido y sin actividad reciente: la sesión terminó mientras
    // la pestaña estaba cerrada. Se limpia sin redirigir (queda como invitado).
    const vencido = this.token && this.expiracionToken() <= Date.now();
    if (vencido && !this.usuarioActivo()) {
      this.limpiarSesionLocal();
      return;
    }

    this.programarRenovacion();
  }

  login(email: string, password: string, remember: boolean = false) {
    const URL = URL_SERVICIOS + '/auth/login_ecommerce';
    return this.http.post(URL, { email, password, remember }).pipe(
      map((resp: any) => this.saveLocalStorage(resp)),
      catchError((err: any) => of(err))
    );
  }

  loginGoogle(credential: string, remember: boolean = false) {
    const URL = URL_SERVICIOS + '/auth/login_google';
    return this.http.post(URL, { credential, remember }).pipe(
      map((resp: any) => this.saveLocalStorage(resp)),
      catchError((err: any) => of(err))
    );
  }

  saveLocalStorage(resp: any): boolean {
    if (resp && resp.access_token) {
      localStorage.setItem(CLAVE_TOKEN, resp.access_token);
      localStorage.setItem(CLAVE_USUARIO, JSON.stringify(resp.user));
      this.token = resp.access_token;
      this.user = resp.user;
      this.sesion.set(true);
      this.registrarActividad(true);
      this.programarRenovacion();
      return true;
    }
    return false;
  }

  isLoggedIn(): boolean {
    return !!this.token;
  }

  // ------------------------------------------------------------------
  // Renovación silenciosa del token
  // ------------------------------------------------------------------

  /**
   * Pide un token nuevo a /auth/refresh. Si ya hay una renovación en curso,
   * devuelve la misma: así, aunque fallen 5 peticiones a la vez, se hace
   * un solo refresh.
   */
  renovarSesion(): Observable<boolean> {
    // Otra pestaña ya renovó: se adopta su token sin pedir otro.
    const tokenGuardado = localStorage.getItem(CLAVE_TOKEN);
    if (tokenGuardado && tokenGuardado !== this.token) {
      this.cargarDesdeAlmacenamiento();
      this.programarRenovacion();
      return of(true);
    }

    if (!this.token) return of(false);

    if (!this.renovacion$) {
      this.renovacion$ = this.http.post(URL_SERVICIOS + '/auth/refresh', {}).pipe(
        map((resp: any) => this.saveLocalStorage(resp)),
        catchError(() => of(false)),
        finalize(() => (this.renovacion$ = undefined)),
        shareReplay(1),
      );
    }
    return this.renovacion$;
  }

  /** ¿Hubo interacción en esta u otra pestaña dentro de la ventana permitida? */
  usuarioActivo(): boolean {
    const guardada = Number(localStorage.getItem(CLAVE_ACTIVIDAD)) || 0;
    const ultima = Math.max(this.ultimaActividad, guardada);
    return Date.now() - ultima < VENTANA_INACTIVIDAD_MS;
  }

  registrarActividad(forzarGuardado = false): void {
    const ahora = Date.now();
    this.ultimaActividad = ahora;

    if (forzarGuardado || ahora - this.ultimoGuardado > INTERVALO_GUARDADO_MS) {
      this.ultimoGuardado = ahora;
      localStorage.setItem(CLAVE_ACTIVIDAD, String(ahora));
    }
  }

  /** Programa la renovación para un poco antes del vencimiento del token. */
  private programarRenovacion(): void {
    clearTimeout(this.temporizador);
    if (!this.token) return;

    const variacion = Math.random() * VARIACION_MAX_MS;
    const espera = Math.max(0, this.expiracionToken() - Date.now() - MARGEN_RENOVACION_MS - variacion);
    const esperaPorTramo = Math.min(espera, MAX_TIMEOUT_MS);

    // Fuera de la zona de Angular: un temporizador largo no debe impedir
    // que la app se considere "estable" (afecta a la hidratación del SSR).
    this.zone.runOutsideAngular(() => {
      this.temporizador = setTimeout(() => {
        this.zone.run(() => {
          if (espera > MAX_TIMEOUT_MS) {
            this.programarRenovacion(); // token largo (Recordarme): siguiente tramo
          } else {
            this.alLlegarMomentoDeRenovar();
          }
        });
      }, esperaPorTramo);
    });
  }

  private alLlegarMomentoDeRenovar(): void {
    if (this.usuarioActivo()) {
      this.renovarSesion().subscribe((ok) => {
        if (!ok) this.sesionExpirada(this.router.url);
      });
      return;
    }
    // Inactivo: se cierra la sesión y queda como invitado en la misma página
    // (si es una página privada, su guard lo manda a /login).
    this.sesionExpirada(this.router.url);
  }

  /** Lee el vencimiento (claim "exp") directamente del JWT. */
  private expiracionToken(): number {
    try {
      const payload = this.token.split('.')[1];
      const json = atob(payload.replace(/-/g, '+').replace(/_/g, '/'));
      return (JSON.parse(json).exp ?? 0) * 1000;
    } catch {
      return 0;
    }
  }

  // ------------------------------------------------------------------
  // Escuchas del navegador
  // ------------------------------------------------------------------

  private escucharActividad(): void {
    const registrar = () => this.registrarActividad();
    // Fuera de Angular: un clic no necesita disparar detección de cambios.
    this.zone.runOutsideAngular(() => {
      for (const evento of ['click', 'keydown', 'scroll', 'touchstart']) {
        window.addEventListener(evento, registrar, { passive: true });
      }
    });
    this.registrarActividad(true); // abrir la página cuenta como actividad
  }

  /** Mantiene sincronizadas todas las pestañas abiertas de la tienda. */
  private escucharOtrasPestanas(): void {
    window.addEventListener('storage', (evento: StorageEvent) => {
      if (evento.key !== CLAVE_TOKEN) return;

      this.zone.run(() => {
        if (evento.newValue) {
          // Otra pestaña inició sesión o renovó el token
          this.cargarDesdeAlmacenamiento();
          this.programarRenovacion();
        } else {
          // Otra pestaña cerró sesión
          this.limpiarSesionLocal();
        }
      });
    });
  }

  // ------------------------------------------------------------------
  // Resto de endpoints de autenticación
  // ------------------------------------------------------------------

  register(data: any) {
    return this.http.post(URL_SERVICIOS + '/auth/register', data);
  }

  verifiedAuth(data: any) {
    return this.http.post(URL_SERVICIOS + '/auth/verified_auth', data);
  }

  verifiedMail(data: any) {
    return this.http.post(URL_SERVICIOS + '/auth/verified_email', data);
  }

  verifiedCode(data: any) {
    return this.http.post(URL_SERVICIOS + '/auth/verified_code', data);
  }

  verifiedNewPassword(data: any) {
    return this.http.post(URL_SERVICIOS + '/auth/new_password', data);
  }

  logout(returnUrl?: string) {
    this.http.post(URL_SERVICIOS + '/auth/logout', {}).subscribe({
      next: () => this.sesionExpirada(returnUrl),
      error: () => this.sesionExpirada(returnUrl),
    });
  }

  sesionExpirada(returnUrl?: string): void {
    this.limpiarSesionLocal();
    setTimeout(() => {
      this.router.navigateByUrl(returnUrl ?? '/login');
    }, 500);
  }

  /** Refresca el usuario guardado sin volver a consultar el backend. */
  actualizarUsuarioLocal(cambios: Partial<any>): void {
    this.user = { ...this.user, ...cambios };
    localStorage.setItem(CLAVE_USUARIO, JSON.stringify(this.user));
  }

  // ------------------------------------------------------------------
  // Helpers privados
  // ------------------------------------------------------------------

  private cargarDesdeAlmacenamiento(): void {
    const token = localStorage.getItem(CLAVE_TOKEN);
    const usuario = localStorage.getItem(CLAVE_USUARIO);
    this.token = token ?? '';
    this.user = token && usuario ? JSON.parse(usuario) : null;
    this.sesion.set(!!this.token);
  }

  private limpiarSesionLocal(): void {
    clearTimeout(this.temporizador);
    localStorage.removeItem(CLAVE_TOKEN);
    localStorage.removeItem(CLAVE_USUARIO);
    this.token = '';
    this.user = null;
    this.sesion.set(false);
  }
}