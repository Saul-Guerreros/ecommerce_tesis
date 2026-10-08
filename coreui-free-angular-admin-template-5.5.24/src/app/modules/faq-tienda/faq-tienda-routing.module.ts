import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { FaqTiendaComponent } from './faq-tienda.component';
import { ListFaqTiendaComponent } from './list-faq-tienda/list-faq-tienda.component';

const routes: Routes = [
  { path: '', component: FaqTiendaComponent, children: [{ path: '', component: ListFaqTiendaComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class FaqTiendaRoutingModule {}