import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CargosComponent } from './cargos.component';
import { ListCargoComponent } from './list-cargo/list-cargo.component';

const routes: Routes = [
  { path: '', component: CargosComponent, children: [{ path: '', component: ListCargoComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class CargosRoutingModule {}