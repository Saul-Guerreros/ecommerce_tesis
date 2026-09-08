import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ProveedoresRoutingModule } from './proveedores-routing.module';
import { ProveedoresComponent } from './proveedores.component';
import { ListProveedorComponent } from './list-proveedor/list-proveedor.component';
import { FormProveedorComponent } from './form-proveedor/form-proveedor.component';
import { DeleteProveedorComponent } from './delete-proveedor/delete-proveedor.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, ProveedoresRoutingModule, ProveedoresComponent,
    ListProveedorComponent, FormProveedorComponent, DeleteProveedorComponent,
  ],
})
export class ProveedoresModule {}