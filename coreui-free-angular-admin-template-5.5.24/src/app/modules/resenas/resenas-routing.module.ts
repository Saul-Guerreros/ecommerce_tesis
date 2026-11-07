import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ResenasComponent } from './resenas.component';
import { ListResenaComponent } from './list-resena/list-resena.component';

const routes: Routes = [
  { path: '', component: ResenasComponent, children: [{ path: '', component: ListResenaComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class ResenasRoutingModule {}