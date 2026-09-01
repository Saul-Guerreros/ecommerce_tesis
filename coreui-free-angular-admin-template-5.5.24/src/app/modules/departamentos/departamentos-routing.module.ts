import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { DepartamentosComponent } from './departamentos.component';
import { ListDepartamentoComponent } from './list-departamento/list-departamento.component';

const routes: Routes = [
  {
    path: '',
    component: DepartamentosComponent,
    children: [{ path: '', component: ListDepartamentoComponent }],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class DepartamentosRoutingModule {}
