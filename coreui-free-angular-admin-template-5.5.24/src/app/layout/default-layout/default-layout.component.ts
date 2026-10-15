import { Component, inject } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';
import { NgScrollbar } from 'ngx-scrollbar';
import { permisoParaUrl } from './_nav-permisos'; 
import { IconDirective } from '@coreui/icons-angular';
import {ContainerComponent,INavData,ShadowOnScrollDirective, SidebarBrandComponent,SidebarComponent,SidebarFooterComponent, SidebarHeaderComponent,SidebarNavComponent,SidebarToggleDirective,SidebarTogglerDirective} from '@coreui/angular';

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
    const visibles = items.reduce<INavData[]>((acc, item) => {
      if (item.children) {
        // Un grupo se muestra si al menos uno de sus hijos es visible
        const hijos = this.filtrarPorPermisos(item.children);
        if (hijos.length) { acc.push({ ...item, children: hijos }); }
      } else if (item.title || this.puedeVer(item.url)) {
        acc.push(item);
      }
      return acc;
    }, []);

    // Quita títulos de sección que quedaron sin ninguna opción debajo
    return visibles.filter((it, i) => !it.title || (visibles[i + 1] !== undefined && !visibles[i + 1].title));
  }

  private puedeVer(url?: string | any[]): boolean {
    if (!url || typeof url !== 'string') return true;
    if (this.authService.tieneRol('Administrador')) return true;
    const requerido = permisoParaUrl(url);
    return !requerido || this.authService.tienePermiso(requerido);
  }

}
