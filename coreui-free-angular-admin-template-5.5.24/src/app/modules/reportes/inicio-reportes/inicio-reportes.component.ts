import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  BadgeComponent, ButtonDirective, CardBodyComponent, CardComponent, CardFooterComponent,
  ColComponent, RowComponent,
} from '@coreui/angular';
import { IconDirective } from '@coreui/icons-angular';

import { AuthService } from '../../../auth/services/auth.service';
import { CATALOGO_REPORTES } from '../reportes.catalogo';

/** Tarjetas de los reportes a los que el usuario tiene acceso. */
@Component({
  selector: 'app-inicio-reportes',
  standalone: true,
  imports: [
    RouterLink, RowComponent, ColComponent, CardComponent, CardBodyComponent,
    CardFooterComponent, ButtonDirective, BadgeComponent, IconDirective,
  ],
  styles: [`
    .tarjeta-reporte {
      border-top: 3px solid var(--color-reporte);
      transition: transform .15s ease, box-shadow .15s ease;
    }
    .tarjeta-reporte:hover {
      transform: translateY(-2px);
      box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .08);
    }
    .icono-reporte {
      width: 44px;
      height: 44px;
      flex-shrink: 0;
      border-radius: .6rem;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--color-reporte);
      background: color-mix(in srgb, var(--color-reporte) 12%, transparent);
    }
    .etiqueta-contenido {
      font-size: .75rem;
      font-weight: 500;
      padding: .3rem .6rem;
      border-radius: 1rem;
      background: var(--cui-tertiary-bg);
      color: var(--cui-body-color);
      border: 1px solid var(--cui-border-color);
    }
  `],
  template: `
    <div class="mb-4">
      <h4 class="mb-0">Reportes</h4>
      <small class="text-body-secondary">Consultá, filtrá y exportá la información del sistema.</small>
    </div>

    @if (reportes.length === 0) {
      <c-card><c-card-body class="text-center text-body-secondary py-5">
        No tenés permisos asignados para ver reportes.
      </c-card-body></c-card>
    }

    <c-row class="g-4">
      @for (r of reportes; track r.clave) {
        <c-col md="6" xl="4">
          <c-card class="h-100 tarjeta-reporte" [style.--color-reporte]="color(r.clave)">
            <c-card-body>
              <div class="d-flex align-items-start gap-3 mb-3">
                <div class="icono-reporte">
                  <svg cIcon [name]="r.icono" size="xl"></svg>
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0">{{ r.titulo }}</h5>
                    @if (!r.disponible) { <c-badge color="secondary">Próximamente</c-badge> }
                  </div>
                  <div class="small text-body-secondary mt-1">{{ r.descripcion }}</div>
                </div>
              </div>

              <div class="d-flex flex-wrap gap-2">
                @for (c of r.contenidos; track c) {
                  <span class="etiqueta-contenido">{{ c }}</span>
                }
              </div>
            </c-card-body>

            <c-card-footer class="bg-transparent border-top-0 pt-0 pb-3">
              <a cButton color="primary" variant="outline" size="sm" class="w-100" [routerLink]="r.ruta"
                 [class.disabled]="!r.disponible" [attr.aria-disabled]="!r.disponible">
                Abrir reporte
              </a>
            </c-card-footer>
          </c-card>
        </c-col>
      }
    </c-row>
  `,
})
export class InicioReportesComponent {
  private readonly auth = inject(AuthService);

  readonly reportes = CATALOGO_REPORTES.filter(
    (r) => this.auth.tieneRol('Administrador') || this.auth.tienePermiso(r.permiso),
  );

  /** Color de acento por reporte (variables de CoreUI: se adaptan al modo oscuro). */
  color(clave: string): string {
    const colores: Record<string, string> = {
      ventas: 'var(--cui-success)',
      inventario: 'var(--cui-info)',
      compras: 'var(--cui-warning)',
      servicio: 'var(--cui-danger)',
      clientes: 'var(--cui-primary)',
      promociones: 'var(--cui-pink, #e83e8c)',
      chatbot: 'var(--cui-indigo, #6610f2)',
    };
    return colores[clave] ?? 'var(--cui-primary)';
  }
}