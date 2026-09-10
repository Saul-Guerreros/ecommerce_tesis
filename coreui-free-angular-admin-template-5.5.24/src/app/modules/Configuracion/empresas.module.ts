import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { EmpresasRoutingModule } from './empresas-routing.module';
import { EmpresasComponent } from './empresas.component';
import { ListEmpresaComponent } from './list-empresa/list-empresa.component';
import { FormEmpresaComponent } from './form-empresa/form-empresa.component';
import { DeleteEmpresaComponent } from './delete-empresa/delete-empresa.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    EmpresasRoutingModule,
    EmpresasComponent,
    ListEmpresaComponent,
    FormEmpresaComponent,
    DeleteEmpresaComponent,
  ],
})
export class EmpresasModule {}
