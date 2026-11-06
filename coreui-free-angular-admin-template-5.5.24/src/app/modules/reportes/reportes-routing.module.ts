import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { ReportesComponent } from './reportes.component';
import { InicioReportesComponent } from './inicio-reportes/inicio-reportes.component';
import { ReporteVentasComponent } from './reporte-ventas/reporte-ventas.component';
import { ReporteInventarioComponent } from './reporte-inventario/reporte-inventario.component';
import { ReporteComprasComponent } from './reporte-compras/reporte-compras.component';
import { ReporteServicioComponent } from './reporte-servicio/reporte-servicio.component';
import { ReporteClientesComponent } from './reporte-clientes/reporte-clientes.component';
import { ReportePromocionesComponent } from './reporte-promociones/reporte-promociones.component';
import { ReporteChatbotComponent } from './reporte-chatbot/reporte-chatbot.component';
import { ReporteComportamientoComponent } from './reporte-comportamiento/reporte-comportamiento.component';

/**
 * El permiso de cada reporte lo valida rutaPermisoGuard (registrado en el
 * layout) con el mapa de _nav-permisos.ts. Los reportes que aún no existen
 * redirigen al inicio.
 */
const routes: Routes = [
  {
    path: '',
    component: ReportesComponent,
    children: [
      { path: '', component: InicioReportesComponent, data: { title: 'Reportes' } },
      { path: 'ventas', component: ReporteVentasComponent, data: { title: 'Ventas' } },
      { path: 'inventario', component: ReporteInventarioComponent, data: { title: 'Inventario' } },
      { path: 'compras', component: ReporteComprasComponent, data: { title: 'Compras' } },
      { path: 'servicio', component: ReporteServicioComponent, data: { title: 'Servicio técnico' } },
      { path: 'clientes', component: ReporteClientesComponent, data: { title: 'Clientes' } },
      { path: 'promociones', component: ReportePromocionesComponent, data: { title: 'Promociones' } },
      { path: 'chatbot', component: ReporteChatbotComponent, data: { title: 'Asistente virtual' } },
      { path: 'comportamiento', component: ReporteComportamientoComponent, data: { title: 'Comportamiento de clientes' } },
      { path: '**', redirectTo: '' },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ReportesRoutingModule {}