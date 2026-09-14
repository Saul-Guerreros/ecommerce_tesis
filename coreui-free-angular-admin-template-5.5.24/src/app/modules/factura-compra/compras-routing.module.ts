import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ComprasComponent } from './compras.component';
import { ListCompraComponent } from './list-compra/list-compra.component';
import { FormCompraComponent } from './form-compra/form-compra.component';

const routes: Routes = [
  {
    path: '', component: ComprasComponent, children: [
      { path: '', component: ListCompraComponent },
      { path: 'nueva', component: FormCompraComponent },
      { path: ':id', component: FormCompraComponent }, // modo ver (solo lectura)
    ]
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class ComprasRoutingModule {}