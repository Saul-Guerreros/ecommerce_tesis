import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { ToastrService } from 'ngx-toastr';
import { catchError, throwError } from 'rxjs';
import { URL_SERVICIOS } from '../../../config/config';
import { AuthService } from './auth.service';

/**
 * Agrega el header Authorization: Bearer <token> a toda peticion dirigida
 * al backend (URL_SERVICIOS). Ademas, si el backend responde 401 estando
 * logueado (token vencido o invalido), cierra la sesion local y redirige
 * a /login con un aviso, en vez de dejar al usuario "logueado" pero con
 * todas las peticiones fallando en silencio.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);
  const toastr = inject(ToastrService);
  const token = authService.token;

  if (token && req.url.startsWith(URL_SERVICIOS)) {
    req = req.clone({
      setHeaders: { Authorization: `Bearer ${token}` },
    });
  }

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401 && req.url.startsWith(URL_SERVICIOS) && authService.isLoggedIn()) {
        toastr.warning('Tu sesión expiró, iniciá sesión de nuevo.', 'Sesión expirada');
        authService.sesionExpirada();
      }
      return throwError(() => error);
    }),
  );
};