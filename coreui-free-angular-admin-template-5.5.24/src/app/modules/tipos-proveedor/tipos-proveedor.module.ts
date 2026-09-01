import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TiposProveedorRoutingModule } from './tipos-proveedor-routing.module';
import { TiposProveedorComponent } from './tipos-proveedor.component';
import { ListTipoProveedorComponent } from './list-tipo-proveedor/list-tipo-proveedor.component';
import { FormTipoProveedorComponent } from './form-tipo-proveedor/form-tipo-proveedor.component';
import { DeleteTipoProveedorComponent } from './delete-tipo-proveedor/delete-tipo-proveedor.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    TiposProveedorRoutingModule,
    TiposProveedorComponent,
    ListTipoProveedorComponent,
    FormTipoProveedorComponent,
    DeleteTipoProveedorComponent,
  ],
})
export class TiposProveedorModule {}