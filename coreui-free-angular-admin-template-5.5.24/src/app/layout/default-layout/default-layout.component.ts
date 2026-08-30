import { Component, inject } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';
import { NgScrollbar } from 'ngx-scrollbar';

import { IconDirective } from '@coreui/icons-angular';
import {
  ContainerComponent,
  INavData,
  ShadowOnScrollDirective,
  SidebarBrandComponent,
  SidebarComponent,
  SidebarFooterComponent,
  SidebarHeaderComponent,
  SidebarNavComponent,
  SidebarToggleDirective,
  SidebarTogglerDirective
} from '@coreui/angular';

import { DefaultFooterComponent, DefaultHeaderComponent } from './';
import { navItems } from './_nav';
import { PERMISO_POR_RUTA } from './_nav-permisos';
import { AuthService } from '../../auth/services/auth.service';

function isOverflown(element: HTMLElement) {
  return (
    element.scrollHeight > element.clientHeight ||
    element.scrollWidth > element.clientWidth
  );
}

@Component({
  selector: 'app-dashboard',
  templateUrl: './default-layout.component.html',
  styleUrls: ['./default-layout.component.scss'],
  imports: [
    SidebarComponent,
    SidebarHeaderComponent,
    SidebarBrandComponent,
    SidebarNavComponent,
    SidebarFooterComponent,
    SidebarToggleDirective,
    SidebarTogglerDirective,
    ContainerComponent,
    DefaultFooterComponent,
    DefaultHeaderComponent,
    IconDirective,
    NgScrollbar,
    RouterOutlet,
    RouterLink,
    ShadowOnScrollDirective
  ]
})
export class DefaultLayoutComponent {
  private authService = inject(AuthService);

  // Filtra los items (y sub-items) cuya url requiere un permiso que el
  // usuario logueado no tiene. Ver PERMISO_POR_RUTA en _nav-permisos.ts.
  public navItems: INavData[] = this.filtrarPorPermisos([...navItems]);

  private filtrarPorPermisos(items: INavData[]): INavData[] {
    return items
      .filter((item) => this.puedeVer(item.url))
      .map((item) => (item.children ? { ...item, children: this.filtrarPorPermisos(item.children) } : item));
  }

  private puedeVer(url?: string | any[]): boolean {
    if (!url || typeof url !== 'string') return true;
    const permisoRequerido = PERMISO_POR_RUTA[url];
    if (!permisoRequerido) return true;
    return this.authService.tienePermiso(permisoRequerido);
  }
}
