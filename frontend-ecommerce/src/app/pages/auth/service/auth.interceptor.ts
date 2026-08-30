import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { URL_SERVICIOS } from '../../../config/config';
import { AuthService } from './auth.service';

/**
 * Agrega el header Authorization: Bearer <token> a toda peticion dirigida
 * al backend (URL_SERVICIOS). Hoy el login/registro/browsing de productos
 * son publicos, pero esto deja listo el terreno para cualquier endpoint que
 * requiera sesion (por ejemplo, un futuro "mis pedidos" o "mi perfil").
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);
  const token = authService.token;

  if (token && req.url.startsWith(URL_SERVICIOS)) {
    req = req.clone({
      setHeaders: { Authorization: `Bearer ${token}` },
    });
  }

  return next(req);
};
