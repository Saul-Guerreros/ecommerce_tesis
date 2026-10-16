import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { ToastrService } from 'ngx-toastr';
import { CarritoService } from '../../../core/services/carrito.service';

/**
 * Protege TODO el wizard de Solicitud de Crédito (/solicitar-credito y
 * sus hijos personal/laboral/referencias/confirmacion): si no hay items
 * en car_modpag = 'credito', no tiene sentido dejar avanzar a ningún
 * paso.
 *
 * Mismo patrón que carritoNoVacioGuard, pero mirando específicamente la
 * sección de crédito y no el carrito completo: un carrito con solo
 * items al contado no debe dejar entrar acá (ese caso lo cubre el otro
 * guard, sobre /proceso-de-pago).
 */
export const carritoCreditoNoVacioGuard: CanActivateFn = () => {
  const carritoService = inject(CarritoService);
  const router = inject(Router);
  const toastr = inject(ToastrService);

  return carritoService.cargar().pipe(
    map((resp) => {
      const hayCredito = resp.data.some((item) => item.car_modpag === 'credito');

      if (hayCredito) {
        return true;
      }

      toastr.info(
        'Tu carrito a crédito está vacío. Agregá productos en esa modalidad para continuar.',
        'Solicitud de Crédito'
      );
      return router.createUrlTree(['/carrito-de-compra']);
    }),
    catchError(() => {
      toastr.error('No pudimos verificar tu carrito.', 'Error');
      return of(router.createUrlTree(['/carrito-de-compra']));
    })
  );
};