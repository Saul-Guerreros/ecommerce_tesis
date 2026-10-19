import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TiposDispositivoComponent } from './tipos-dispositivo.component';
import { ListTipoDispositivoComponent } from './list-tipo-dispositivo/list-tipo-dispositivo.component';

const routes: Routes = [
  { path: '', component: TiposDispositivoComponent, children: [{ path: '', component: ListTipoDispositivoComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class TiposDispositivoRoutingModule {}
