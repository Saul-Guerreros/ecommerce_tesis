import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TrabajosComponent } from './trabajos.component';
import { ListTrabajoComponent } from './list-trabajo/list-trabajo.component';

const routes: Routes = [
  { path: '', component: TrabajosComponent, children: [{ path: '', component: ListTrabajoComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class TrabajosRoutingModule {}
