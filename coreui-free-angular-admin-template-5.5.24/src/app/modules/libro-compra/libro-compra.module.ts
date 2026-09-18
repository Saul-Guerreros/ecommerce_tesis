import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { LibroCompraRoutingModule } from './libro-compra-routing.module';
import { LibroCompraComponent } from './libro-compra.component';
import { ListLibroCompraComponent } from './list-libro-compra/list-libro-compra.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, LibroCompraRoutingModule, LibroCompraComponent, ListLibroCompraComponent],
})
export class LibroCompraModule {}