import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { map, catchError, of } from 'rxjs';
import { ToastrService } from 'ngx-toastr';
import { CarritoService } from '../../../core/services/carrito.service';

/**
 * Protege TODO el flujo de checkout (proceso-de-pago y sus hijos entrega/
 * facturacion/confirmacion): si el carrito está vacío, no tiene sentido
 * dejar avanzar a ningún paso. Se dispara, por ejemplo, justo después de
 * confirmar un pedido (el carrito se vacía en el backend) si el usuario
 * retrocede e intenta re-entrar al checkout.
 */
export const carritoNoVacioGuard: CanActivateFn = () => {
  const carritoService = inject(CarritoService);
  const router = inject(Router);
  const toastr = inject(ToastrService);

  return carritoService.cargar().pipe(
    map((resp) => {
      if (resp.data.length > 0) {
        return true;
      }
      toastr.info('Tu carrito está vacío. Agregá productos para continuar.', 'Checkout');
      return router.createUrlTree(['/carrito-de-compra']);
    }),
    catchError(() => {
      toastr.error('No pudimos verificar tu carrito.', 'Error');
      return of(router.createUrlTree(['/carrito-de-compra']));
    }),
  );
};