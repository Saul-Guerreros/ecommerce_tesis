import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { EmpresasComponent } from './empresas.component';
import { ListEmpresaComponent } from './list-empresa/list-empresa.component';

const routes: Routes = [
  {
    path: '',
    component: EmpresasComponent,
    children: [
      { path: '', component: ListEmpresaComponent },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class EmpresasRoutingModule {}
