import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TiposProveedorComponent } from './tipos-proveedor.component';
import { ListTipoProveedorComponent } from './list-tipo-proveedor/list-tipo-proveedor.component';

const routes: Routes = [
  {
    path: '',
    component: TiposProveedorComponent,
    children: [{ path: '', component: ListTipoProveedorComponent }],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class TiposProveedorRoutingModule {}