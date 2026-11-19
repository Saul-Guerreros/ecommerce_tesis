import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { ToastrService } from 'ngx-toastr';
import { CreditoService } from '../../../core/services/credito.service';

/**
 * Corta la entrada al wizard de Solicitud de Crédito si el cliente ya
 * tiene una solicitud en curso, ANTES de que llene ningún formulario.
 *
 * Vive en la ruta y no en cada botón porque hay dos botones "Solicitar
 * crédito" (página del carrito y panel lateral) y además se puede entrar
 * por URL: un guard los cubre a todos.
 *
 * Si la consulta falla, deja pasar. No es descuido: la barrera real es
 * la validación de generar() en el backend, y bloquear el wizard por un
 * error de red castigaría a clientes que sí pueden solicitar.
 */
export const sinSolicitudEnCursoGuard: CanActivateFn = () => {
  const credito = inject(CreditoService);
  const router = inject(Router);
  const toastr = inject(ToastrService);

  return credito.disponibilidad().pipe(
    map(({ data }) => {
      if (data.disponible) {
        return true;
      }

      const numero = data.solicitud?.numero ? ` (${data.solicitud.numero})` : '';

      toastr.warning(
        `Ya tenés una solicitud de crédito en curso${numero}. Esperá su resolución antes de generar otra.`,
        'Solicitud de Crédito'
      );

      return router.createUrlTree(['/mis-solicitudes-credito']);
    }),
    catchError(() => of(true))
  );
};