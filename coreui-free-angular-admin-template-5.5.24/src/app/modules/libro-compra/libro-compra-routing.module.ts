import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { LibroCompraComponent } from './libro-compra.component';
import { ListLibroCompraComponent } from './list-libro-compra/list-libro-compra.component';

const routes: Routes = [
  {
    path: '',
    component: LibroCompraComponent,
    children: [
      { path: '', component: ListLibroCompraComponent },
    ],
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class LibroCompraRoutingModule {}