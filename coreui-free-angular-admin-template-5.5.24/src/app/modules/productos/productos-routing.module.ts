import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ProductosComponent } from './productos.component';
import { ListProductoComponent } from './list-producto/list-producto.component';

const routes: Routes = [
  { path: '', component: ProductosComponent, children: [{ path: '', component: ListProductoComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class ProductosRoutingModule {}