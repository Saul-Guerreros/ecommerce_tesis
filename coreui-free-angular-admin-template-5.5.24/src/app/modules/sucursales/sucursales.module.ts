import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { SucursalesRoutingModule } from './sucursales-routing.module';
import { SucursalesComponent } from './sucursales.component';
import { ListSucursalComponent } from './list-sucursal/list-sucursal.component';
import { FormSucursalComponent } from './form-sucursal/form-sucursal.component';
import { DeleteSucursalComponent } from './delete-sucursal/delete-sucursal.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    SucursalesRoutingModule,
    SucursalesComponent,
    ListSucursalComponent,
    FormSucursalComponent,
    DeleteSucursalComponent,
  ],
})
export class SucursalesModule {}