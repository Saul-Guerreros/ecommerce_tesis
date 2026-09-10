import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

/**
 * Factory de guard: exige que el usuario logueado tenga AL MENOS UNO de los
 * permisos indicados (mismo criterio que el middleware `permiso` del backend).
 *
 * Uso en las rutas:
 *   {
 *     path: 'atributos',
 *     loadChildren: () => import('./atributos.routes').then(m => m.ATRIBUTOS_ROUTES),
 *     canActivate: [permissionGuard('atributos.ver')]
 *   }
 */
export function permissionGuard(...permisosRequeridos: string[]): CanActivateFn {
  return () => {
    const auth = inject(AuthService);
    const router = inject(Router);

    if (!auth.isLoggedIn()) {
      router.navigate(['/login']);
      return false;
    }

    const tienePermiso = permisosRequeridos.some((p) => auth.tienePermiso(p));

    if (!tienePermiso) {
      router.navigate(['/dashboard']);
      return false;
    }

    return true;
  };
}
