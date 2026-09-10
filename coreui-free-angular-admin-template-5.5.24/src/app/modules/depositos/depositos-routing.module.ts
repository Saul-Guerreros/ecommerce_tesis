import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { DepositosComponent } from './depositos.component';
import { ListDepositoComponent } from './list-deposito/list-deposito.component';

const routes: Routes = [
  {
    path: '',
    component: DepositosComponent,
    children: [{ path: '', component: ListDepositoComponent }],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class DepositosRoutingModule {}