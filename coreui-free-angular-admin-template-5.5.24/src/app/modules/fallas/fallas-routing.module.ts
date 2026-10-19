import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { FallasComponent } from './fallas.component';
import { ListFallaComponent } from './list-falla/list-falla.component';

const routes: Routes = [
  { path: '', component: FallasComponent, children: [{ path: '', component: ListFallaComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class FallasRoutingModule {}
