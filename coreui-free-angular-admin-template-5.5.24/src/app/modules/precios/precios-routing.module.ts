import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PreciosComponent } from './precios.component';
import { ListPrecioComponent } from './list-precio/list-precio.component';

const routes: Routes = [
  { path: '', component: PreciosComponent, children: [{ path: '', component: ListPrecioComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class PreciosRoutingModule {}