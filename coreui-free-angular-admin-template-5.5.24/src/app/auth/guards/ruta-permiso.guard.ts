import { inject } from '@angular/core';
import { CanActivateChildFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { permisoParaUrl } from '../../layout/default-layout/_nav-permisos';

/**
 * Guard único para todas las pantallas del admin: consulta el mapa
 * url → permiso (_nav-permisos.ts), el mismo que usa el sidebar.
 * Si la url no está en el mapa, deja pasar. El Administrador siempre pasa
 * (igual que el middleware `permiso` del backend).
 *
 * Se registra una sola vez en la ruta del layout (app.routes.ts):
 *   canActivateChild: [rutaPermisoGuard]
 */
export const rutaPermisoGuard: CanActivateChildFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (auth.tieneRol('Administrador')) {
    return true;
  }

  const requerido = permisoParaUrl(state.url);
  if (!requerido || auth.tienePermiso(requerido)) {
    return true;
  }

  return router.createUrlTree(['/dashboard']);
};
