import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ProveedoresComponent } from './proveedores.component';
import { ListProveedorComponent } from './list-proveedor/list-proveedor.component';

const routes: Routes = [
  { path: '', component: ProveedoresComponent, children: [{ path: '', component: ListProveedorComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class ProveedoresRoutingModule {}