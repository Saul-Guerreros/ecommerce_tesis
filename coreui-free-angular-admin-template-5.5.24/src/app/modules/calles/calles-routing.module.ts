import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CallesComponent } from './calles.component';
import { ListCalleComponent } from './list-calle/list-calle.component';

const routes: Routes = [
  {
    path: '',
    component: CallesComponent,
    children: [{ path: '', component: ListCalleComponent }],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class CallesRoutingModule {}
