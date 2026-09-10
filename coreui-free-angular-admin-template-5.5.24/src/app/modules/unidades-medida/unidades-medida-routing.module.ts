// unidades-medida-routing.module.ts
import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { UnidadesMedidaComponent } from './unidades-medida.component';
import { ListUnidadMedidaComponent } from './list-unidad-medida/list-unidad-medida.component';

const routes: Routes = [
  { path: '', component: UnidadesMedidaComponent, children: [{ path: '', component: ListUnidadMedidaComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class UnidadesMedidaRoutingModule {}