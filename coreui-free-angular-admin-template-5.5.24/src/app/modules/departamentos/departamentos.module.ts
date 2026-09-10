import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { DepartamentosRoutingModule } from './departamentos-routing.module';
import { DepartamentosComponent } from './departamentos.component';
import { ListDepartamentoComponent } from './list-departamento/list-departamento.component';
import { FormDepartamentoComponent } from './form-departamento/form-departamento.component';
import { DeleteDepartamentoComponent } from './delete-departamento/delete-departamento.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    DepartamentosRoutingModule,
    DepartamentosComponent,
    ListDepartamentoComponent,
    FormDepartamentoComponent,
    DeleteDepartamentoComponent,
  ],
})
export class DepartamentosModule {}
