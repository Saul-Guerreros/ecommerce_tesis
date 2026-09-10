import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CiudadesComponent } from './ciudades.component';
import { ListCiudadComponent } from './list-ciudad/list-ciudad.component';

const routes: Routes = [
  {
    path: '',
    component: CiudadesComponent,
    children: [{ path: '', component: ListCiudadComponent }],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class CiudadesRoutingModule {}