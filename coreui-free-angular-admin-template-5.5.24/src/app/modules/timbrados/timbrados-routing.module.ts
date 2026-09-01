import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TimbradosComponent } from './timbrados.component';
import { ListTimbradoComponent } from './list-timbrado/list-timbrado.component';

const routes: Routes = [
  {
    path: '',
    component: TimbradosComponent,
    children: [{ path: '', component: ListTimbradoComponent }],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class TimbradosRoutingModule {}
