import { HttpErrorResponse, HttpInterceptorFn, HttpRequest } from '@angular/common/http';
import { inject } from '@angular/core';
import { ToastrService } from 'ngx-toastr';
import { catchError, switchMap, throwError } from 'rxjs';
import { URL_SERVICIOS } from '../../../config/config';
import { AuthService } from './auth.service';
import { ActividadTiendaService } from '../../../core/services/actividad-tienda.service';

/**
 * Endpoints donde un 401 NO debe intentar renovar el token
 * (credenciales incorrectas, el propio refresh, logout...).
 */
const RUTAS_SIN_RENOVACION = [
  '/auth/login',
  '/auth/login_ecommerce',
  '/auth/login_google',
  '/auth/refresh',
  '/auth/logout',
  '/auth/register',
];

/**
 * 1. Agrega "Authorization: Bearer <token>" a las peticiones al backend.
 * 2. Si el backend responde 401 con sesión iniciada, renueva el token
 *    UNA vez y reintenta la petición original. Si no se puede renovar,
 *    cierra la sesión con un aviso.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);
  const toastr = inject(ToastrService);
  const actividad = inject(ActividadTiendaService);
  const esBackend = req.url.startsWith(URL_SERVICIOS);

  return next(conToken(req, authService.token, actividad.visitante())).pipe(
    catchError((error: HttpErrorResponse) => {
      const debeRenovar =
        error.status === 401 &&
        esBackend &&
        authService.isLoggedIn() &&
        !RUTAS_SIN_RENOVACION.some((ruta) => req.url.includes(ruta));

      if (!debeRenovar) {
        return throwError(() => error);
      }

      // Inactivo: no se revive la sesión.
      if (!authService.usuarioActivo()) {
        toastr.warning('Tu sesión expiró por inactividad. Iniciá sesión de nuevo.', 'Sesión expirada');
        authService.sesionExpirada();
        return throwError(() => error);
      }

      return authService.renovarSesion().pipe(
        switchMap((renovado) => {
          if (renovado) {
            // Reintento único con el token nuevo (sin volver a capturar el 401).
            return next(conToken(req, authService.token, actividad.visitante()));
          }
          toastr.warning('Tu sesión expiró, iniciá sesión de nuevo.', 'Sesión expirada');
          authService.sesionExpirada();
          return throwError(() => error);
        }),
      );
    }),
  );
};

/** Agrega el JWT (si hay sesión) y el id anónimo del visitante, solo hacia el backend. */
function conToken(req: HttpRequest<unknown>, token: string, visitante: string): HttpRequest<unknown> {
  if (!req.url.startsWith(URL_SERVICIOS)) return req;

  const headers: Record<string, string> = { 'X-Visitante': visitante };
  if (token) headers['Authorization'] = `Bearer ${token}`;

  return req.clone({ setHeaders: headers });
}