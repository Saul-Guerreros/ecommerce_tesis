import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { GestionCuotasComponent } from './gestion-cuotas.component';
import { ListProductoCuotaComponent } from './list-producto-cuota/list-producto-cuota.component';

const routes: Routes = [
  {
    path: '',
    component: GestionCuotasComponent,
    children: [{ path: '', component: ListProductoCuotaComponent }],
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class GestionCuotasRoutingModule {}