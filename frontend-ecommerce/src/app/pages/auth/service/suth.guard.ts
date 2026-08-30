import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

/**
 * Antes era un stub que siempre devolvia true (no protegia nada). Ahora
 * exige sesion iniciada; listo para usarse en futuras rutas privadas del
 * ecommerce (ej. "Mi cuenta", "Mis pedidos"):
 *
 *   { path: 'mi-cuenta', component: MiCuentaComponent, canActivate: [suthGuard] }
 */
export const suthGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (authService.isLoggedIn()) {
    return true;
  }

  router.navigateByUrl('/login');
  return false;
};
