import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { BarriosComponent } from './barrios.component';
import { ListBarrioComponent } from './list-barrio/list-barrio.component';

const routes: Routes = [
  {
    path: '',
    component: BarriosComponent,
    children: [{ path: '', component: ListBarrioComponent }],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class BarriosRoutingModule {}
