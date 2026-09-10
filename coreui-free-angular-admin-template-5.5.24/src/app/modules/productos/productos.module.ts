import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ProductosRoutingModule } from './productos-routing.module';
import { ProductosComponent } from './productos.component';
import { ListProductoComponent } from './list-producto/list-producto.component';
import { FormProductoComponent } from './form-producto/form-producto.component';
import { DeleteProductoComponent } from './delete-producto/delete-producto.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, ProductosRoutingModule, ProductosComponent,
    ListProductoComponent, FormProductoComponent, DeleteProductoComponent,
  ],
})
export class ProductosModule {}