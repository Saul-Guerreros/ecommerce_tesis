import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CuentasPorCobrarComponent } from './cuentas-por-cobrar.component';
import { ListCuentasPorCobrarComponent } from './list-cuentas-por-cobrar/list-cuentas-por-cobrar.component';

const routes: Routes = [
  {
    path: '',
    component: CuentasPorCobrarComponent,
    children: [
      { path: '', component: ListCuentasPorCobrarComponent },
    ],
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class CuentasPorCobrarRoutingModule {}