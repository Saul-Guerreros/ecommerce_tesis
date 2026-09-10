import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { RolesComponent } from './roles.component';
import { ListRolComponent } from './list-rol/list-rol.component';

const routes: Routes = [
  { path: '', component: RolesComponent, children: [{ path: '', component: ListRolComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class RolesRoutingModule {}